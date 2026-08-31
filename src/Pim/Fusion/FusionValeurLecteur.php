<?php

declare(strict_types=1);

namespace App\Pim\Fusion;

use App\Audit\ValueNormalizer;
use App\Pim\Entity\Fiche;
use App\Pim\Import\Schema\ColumnDefinition;

/**
 * Lecture des valeurs NATIVES d'un champ du catalogue de fusion (l'écran de
 * comparaison affiche les libellés via FicheExportValueReader, la copie entre
 * fiches passe par les valeurs natives — zéro perte de format, zéro
 * résolution LOV). Même résolution de cible que l'import en masse
 * (FicheImportRowProcessor), plus la sentinelle « fiche » du catalogue.
 */
final readonly class FusionValeurLecteur
{
    public function __construct(private ValueNormalizer $normalizer)
    {
    }

    public function native(ColumnDefinition $column, object $aggregate, Fiche $fiche): mixed
    {
        $cible = $this->cible($column, $aggregate, $fiche);
        if (null === $cible) {
            return null;
        }
        if (!method_exists($cible, $column->target)) {
            throw new \LogicException(sprintf('Getter %s absent sur %s (colonne %s).', $column->target, $cible::class, $column->header));
        }

        return $cible->{$column->target}();
    }

    /** Deux valeurs sont équivalentes pour la fusion : mêmes une fois normalisées, ou toutes deux vides. */
    public function identiques(mixed $a, mixed $b): bool
    {
        if (self::vide($a) && self::vide($b)) {
            return true;
        }

        return $this->normalizer->same($a, $b);
    }

    public static function vide(mixed $valeur): bool
    {
        return null === $valeur
            || (is_string($valeur) && '' === trim($valeur))
            || (is_array($valeur) && [] === $valeur);
    }

    private function cible(ColumnDefinition $column, object $aggregate, Fiche $fiche): ?object
    {
        return match ($column->targetPath) {
            null => $aggregate,
            'localisation' => $fiche->localisation(),
            FusionChampsCatalogue::CIBLE_FICHE => $fiche,
            default => $aggregate->{$column->targetPath}(),
        };
    }
}
