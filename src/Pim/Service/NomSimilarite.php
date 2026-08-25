<?php

declare(strict_types=1);

namespace App\Pim\Service;

use function Symfony\Component\String\u;

/**
 * Similarité de dénominations pour les rapprochements d'enrichissement
 * (Sirene, Geoapify, DATAtourisme) : minuscules puis translittération ASCII,
 * car similar_text compte des octets et pénaliserait les accents UTF-8.
 */
final class NomSimilarite
{
    /** Seuil commun en dessous duquel un rapprochement par nom n'est pas fiable. */
    public const SEUIL_DEFAUT = 0.82;

    public static function score(string $a, string $b): float
    {
        $a = self::normalise($a);
        $b = self::normalise($b);
        if ('' === $a || '' === $b) {
            return 0.0;
        }
        similar_text($a, $b, $pourcent);

        return $pourcent / 100;
    }

    private static function normalise(string $valeur): string
    {
        return u($valeur)->trim()->lower()->ascii()->toString();
    }
}
