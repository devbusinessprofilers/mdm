<?php

declare(strict_types=1);

namespace App\Enrichment\Service;

use App\Enrichment\Entity\FicheTranslation;
use App\Enrichment\Enum\SupportedLocale;
use App\Enrichment\Message\TranslatePublishedFiche;
use App\Enrichment\Repository\FicheTranslationRepository;
use App\Pim\Entity\Fiche;
use App\Pim\Enum\StatutFiche;
use App\Shared\Outbox\OutboxPublisherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

final readonly class FicheTranslationScheduler
{
    public function __construct(
        private FicheTranslationSourceExtractor $extractor,
        private FicheTranslationRepository $translations,
        private EntityManagerInterface $entityManager,
        private OutboxPublisherInterface $outbox,
    ) {
    }

    public function schedule(Fiche $fiche, bool $memeNonPubliee = false): int
    {
        if (!$memeNonPubliee && StatutFiche::Publiee !== $fiche->status()) {
            return 0;
        }
        $sources = $this->extractor->extract($fiche);
        $paths = array_fill_keys(array_map(static fn (TranslationSource $source): string => $source->path, $sources), true);
        // Une seule requête : les traductions existantes, indexées par champ et locale.
        $existing = $this->translations->forFiche($fiche);
        $byKey = [];
        foreach ($existing as $translation) {
            $byKey[$translation->fieldPath().'|'.$translation->locale()->value] = $translation;
        }
        $scheduled = 0;
        foreach (SupportedLocale::targets() as $locale) {
            $token = (string) new Ulid();
            $localeScheduled = false;
            foreach ($sources as $source) {
                $translation = $byKey[$source->path.'|'.$locale->value] ?? null;
                if (!$translation instanceof FicheTranslation) {
                    $translation = new FicheTranslation($fiche, $source->path, $source->label, $locale, $source->value);
                    $this->entityManager->persist($translation);
                    $byKey[$source->path.'|'.$locale->value] = $translation;
                }
                $localeScheduled = $translation->schedule($source->label, $source->value, $token) || $localeScheduled;
            }
            if ($localeScheduled) {
                $this->outbox->enqueue(new TranslatePublishedFiche($fiche->idString(), $locale->value, $token));
                ++$scheduled;
            }
        }
        foreach ($existing as $translation) {
            if (!isset($paths[$translation->fieldPath()])) {
                $translation->markObsolete();
            }
        }

        return $scheduled;
    }
}
