<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Entity\Fiche;
use App\Pim\Entity\TextDuplicateAlert;
use App\Pim\Entity\TextFingerprint;
use App\Pim\Enum\DuplicateReviewStatus;
use App\Pim\Enum\TextDuplicateKind;
use App\Pim\Repository\TextDuplicateAlertRepository;
use App\Pim\Repository\TextFingerprintRepository;
use App\Pim\Repository\TextSimhashBandRepository;
use App\Shared\Service\ParametreProviderInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Détection des doublons de texte d'une fiche, champ par champ. Transposition
 * texte de MediaAnalysisService : empreinte exacte d'abord, puis quasi-doublon
 * par SimHash + banding, avec création / rafraîchissement / résolution des
 * alertes. N'altère jamais la fiche (aucune transition de workflow).
 */
final readonly class TextDuplicateDetector
{
    private const SNIPPET_LENGTH = 200;

    public function __construct(
        private TextDuplicateFields $fields,
        private TextFingerprintCalculator $calculator,
        private TextFingerprintRepository $fingerprints,
        private TextSimhashBandRepository $bands,
        private TextDuplicateAlertRepository $alerts,
        private EntityManagerInterface $entityManager,
        private ParametreProviderInterface $parametres,
    ) {
    }

    public function analyze(Fiche $fiche): void
    {
        $minLength = max(1, $this->parametres->int('pim.longueur_min_texte_doublon'));
        $threshold = max(0, $this->parametres->int('pim.seuil_distance_simhash'));
        foreach ($this->fields->forFiche($fiche) as $field) {
            $this->analyzeField($fiche, $field['path'], $field['label'], $field['text'], $minLength, $threshold);
        }
    }

    private function analyzeField(Fiche $fiche, string $path, string $label, ?string $text, int $minLength, int $threshold): void
    {
        $normalized = null === $text ? '' : $this->calculator->normalize($text);
        $existing = $this->fingerprints->findOneByFicheAndField($fiche->idString(), $path);

        // Champ vidé ou trop court pour être discriminant : l'empreinte (et,
        // par cascade, ses bandes et son alerte) n'a plus lieu d'être.
        if ($this->calculator->length($normalized) < $minLength) {
            if (null !== $existing) {
                $this->bands->deleteForFingerprint($existing->id());
                $this->entityManager->remove($existing);
                $this->entityManager->flush();
            }

            return;
        }

        $exactHash = $this->calculator->exactHash($normalized);
        $simhash = $this->calculator->simhash($normalized);
        $snippet = $this->snippet($text ?? '');

        if (null === $existing) {
            $fingerprint = new TextFingerprint($fiche->idString(), $fiche->type()->value, $path, $label, $exactHash, $simhash, $this->calculator->length($normalized), $snippet);
            $this->entityManager->persist($fingerprint);
        } else {
            $fingerprint = $existing;
            $fingerprint->refresh($label, $exactHash, $simhash, $this->calculator->length($normalized), $snippet);
        }
        $this->entityManager->flush();

        [$duplicate, $kind, $distance] = $this->findDuplicate($fingerprint, $exactHash, $simhash, $threshold);

        $alert = $this->alerts->findForFingerprint($fingerprint);
        if (null !== $duplicate && null !== $kind) {
            if (null === $alert) {
                $this->entityManager->persist(new TextDuplicateAlert($fingerprint, $duplicate, $kind, $distance));
            } else {
                $alert->refresh($duplicate, $kind, $distance);
            }
        } elseif (null !== $alert && DuplicateReviewStatus::Pending === $alert->status()) {
            $alert->resolve('system');
        }

        $this->entityManager->flush();
        $this->bands->replace($fingerprint->id(), $simhash);
    }

    /**
     * @return array{TextFingerprint|null, TextDuplicateKind|null, int|null}
     */
    private function findDuplicate(TextFingerprint $fingerprint, string $exactHash, string $simhash, int $threshold): array
    {
        $exact = $this->fingerprints->findOldestExactMatchOnOtherFiche($exactHash, $fingerprint->ficheId());
        if (null !== $exact) {
            return [$exact, TextDuplicateKind::Exact, 0];
        }

        $best = null;
        $bestDistance = $threshold + 1;
        foreach ($this->fingerprints->findByStringIds($this->bands->candidateIds($simhash, $fingerprint->id())) as $candidate) {
            if ($candidate->ficheId() === $fingerprint->ficheId()) {
                continue;
            }
            $distance = $this->calculator->distance($simhash, $candidate->simhash());
            if ($distance > $threshold) {
                continue;
            }
            if (null === $best || $distance < $bestDistance || ($distance === $bestDistance && $candidate->id() < $best->id())) {
                $best = $candidate;
                $bestDistance = $distance;
            }
        }

        return null === $best ? [null, null, null] : [$best, TextDuplicateKind::Near, $bestDistance];
    }

    private function snippet(string $text): ?string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? $text);
        if ('' === $text) {
            return null;
        }

        return mb_strlen($text) > self::SNIPPET_LENGTH
            ? mb_substr($text, 0, self::SNIPPET_LENGTH - 1).'…'
            : $text;
    }
}
