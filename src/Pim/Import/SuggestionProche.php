<?php

declare(strict_types=1);

namespace App\Pim\Import;

use function Symfony\Component\String\u;

/**
 * Suggestion « Vouliez-vous dire… » des rapports d'erreur d'import : candidat
 * le plus proche de la saisie par distance d'édition sur formes normalisées
 * (minuscules + ASCII — levenshtein compte des octets, les accents UTF-8
 * fausseraient la distance).
 */
final class SuggestionProche
{
    /**
     * @param iterable<string> $candidats
     *
     * @return string|null le candidat original le plus proche, ou null si
     *                     aucun n'est assez proche pour être une suggestion crédible
     */
    public static function trouver(string $saisie, iterable $candidats): ?string
    {
        $saisieNorm = self::normalise($saisie);
        if ('' === $saisieNorm) {
            return null;
        }

        $seuil = max(2, (int) floor(mb_strlen($saisieNorm) * 0.4));
        $meilleur = null;
        $meilleureDistance = PHP_INT_MAX;
        foreach ($candidats as $candidat) {
            $candidatNorm = self::normalise($candidat);
            if ('' === $candidatNorm) {
                continue;
            }
            $distance = levenshtein($saisieNorm, $candidatNorm);
            if (0 === $distance) {
                return $candidat;
            }
            // Premier des ex æquo retenu : rendu déterministe.
            if ($distance < $meilleureDistance) {
                $meilleureDistance = $distance;
                $meilleur = $candidat;
            }
        }

        return $meilleureDistance <= $seuil ? $meilleur : null;
    }

    private static function normalise(string $valeur): string
    {
        return u($valeur)->trim()->lower()->ascii()->toString();
    }
}
