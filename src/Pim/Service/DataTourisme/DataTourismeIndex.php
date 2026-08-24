<?php

declare(strict_types=1);

namespace App\Pim\Service\DataTourisme;

/**
 * Index en mémoire des POI DATAtourisme par code postal, pour rapprocher une
 * fiche PIM de son équivalent touristique. Le rapprochement se fait dans le
 * seau du code postal, par similarité de nom (seuil élevé) — jamais entre CP
 * différents, pour éviter les faux positifs.
 */
final class DataTourismeIndex
{
    /** Similarité minimale nom PIM ↔ nom DATAtourisme. */
    public const SEUIL_RAPPROCHEMENT = 0.82;

    /** @var array<string, list<DataTourismePoi>> */
    private array $parCodePostal = [];

    /** @var array<string, true>|null codes postaux à indexer (borne la mémoire) ; null = tous */
    private ?array $codesAutorises = null;

    /** @param list<string> $codesPostaux restreint l'indexation à ces codes postaux (mémoire) */
    public function __construct(array $codesPostaux = [])
    {
        if ([] !== $codesPostaux) {
            $this->codesAutorises = array_fill_keys($codesPostaux, true);
        }
    }

    public function ajouter(DataTourismePoi $poi): void
    {
        if (null === $poi->codePostal) {
            return;
        }
        if (null !== $this->codesAutorises && !isset($this->codesAutorises[$poi->codePostal])) {
            return;
        }
        $this->parCodePostal[$poi->codePostal][] = $poi;
    }

    /**
     * @param iterable<DataTourismePoi> $pois
     * @param list<string>              $codesPostaux restreint l'indexation (mémoire)
     */
    public static function depuis(iterable $pois, array $codesPostaux = []): self
    {
        $index = new self($codesPostaux);
        foreach ($pois as $poi) {
            $index->ajouter($poi);
        }

        return $index;
    }

    public function estVide(): bool
    {
        return [] === $this->parCodePostal;
    }

    /** Meilleur POI du même code postal dont le nom concorde, ou null. */
    public function rapprocher(string $nom, ?string $codePostal): ?DataTourismePoi
    {
        if (null === $codePostal || '' === trim($nom)) {
            return null;
        }
        $meilleur = null;
        $meilleurScore = self::SEUIL_RAPPROCHEMENT;
        foreach ($this->parCodePostal[$codePostal] ?? [] as $poi) {
            $score = self::similarite($nom, $poi->nom);
            if ($score >= $meilleurScore) {
                $meilleur = $poi;
                $meilleurScore = $score;
            }
        }

        return $meilleur;
    }

    private static function similarite(string $a, string $b): float
    {
        $a = mb_strtolower(trim($a));
        $b = mb_strtolower(trim($b));
        if ('' === $a || '' === $b) {
            return 0.0;
        }
        similar_text($a, $b, $pourcent);

        return $pourcent / 100;
    }
}
