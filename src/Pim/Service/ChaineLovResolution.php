<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Lov\LieuLovCatalog;

/**
 * Résolution d'un libellé d'enseigne vers son code de la LOV « Groupe et
 * chaîne hôtelière » (casse et accents ignorés). Partagée entre l'arbitrage
 * (accepter = sélectionner le code, ou créer la valeur s'il n'existe pas) et
 * l'affichage des suggestions (mention « créera une nouvelle entrée »).
 */
final class ChaineLovResolution
{
    public const ATTRIBUT = 'GENERALE_CHAINES_GROUPE_HOT';

    public static function codePour(string $libelle): ?string
    {
        $cible = self::normalise($libelle);
        foreach (LieuLovCatalog::choicesFor(self::ATTRIBUT) as $code => $label) {
            if (self::normalise($label) === $cible) {
                return $code;
            }
        }

        return null;
    }

    /** Vrai quand accepter cette valeur créera une nouvelle entrée dans la liste. */
    public static function creeraUneEntree(?string $libelle): bool
    {
        return null !== $libelle && '' !== trim($libelle) && null === self::codePour($libelle);
    }

    /** Comparaison de libellés insensible à la casse et aux accents. */
    public static function normalise(string $valeur): string
    {
        $translit = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $valeur);

        return mb_strtolower(trim(false === $translit ? $valeur : $translit));
    }
}
