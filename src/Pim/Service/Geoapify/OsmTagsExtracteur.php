<?php

declare(strict_types=1);

namespace App\Pim\Service\Geoapify;

use App\Pim\Service\PlaceAttributs;

/**
 * Traduit les tags OpenStreetMap bruts d'un lieu (Geoapify Place Details)
 * en attributs exploitables par les vérificateurs d'enrichissement. Pure
 * fonction : aucune I/O.
 */
final class OsmTagsExtracteur
{
    /** @param array<string, mixed> $raw tags OSM bruts */
    public static function extraire(array $raw): PlaceAttributs
    {
        $tag = static fn (string $cle): ?string => is_string($raw[$cle] ?? null) && '' !== trim($raw[$cle]) ? strtolower(trim($raw[$cle])) : null;
        $cuisines = null === $tag('cuisine') ? [] : array_values(array_filter(array_map('trim', explode(';', $tag('cuisine')))));
        $regimes = [];
        foreach (['vegan', 'vegetarian', 'halal', 'kosher'] as $regime) {
            if (in_array($tag('diet:'.$regime), ['yes', 'only'], true)) {
                $regimes[] = $regime;
            }
        }
        if (in_array($tag('organic'), ['yes', 'only'], true)) {
            $regimes[] = 'organic';
        }

        return new PlaceAttributs(
            cuisines: $cuisines,
            regimes: $regimes,
            accesPmr: self::triState($tag('wheelchair'), ['yes', 'limited', 'designated']),
            toilettesPmr: self::triState($tag('toilets:wheelchair'), ['yes']),
            terrasse: self::triState($tag('outdoor_seating'), ['yes']),
            climatisation: self::triState($tag('air_conditioning'), ['yes']),
            wifi: self::triState($tag('internet_access'), ['yes', 'wlan', 'wifi']),
            siteWeb: self::premiereChaine($raw, ['website', 'contact:website', 'url']),
            // `brand` seulement : `operator` est l'exploitant, pas l'enseigne.
            marque: self::premiereChaine($raw, ['brand']),
            etoiles: self::etoiles($tag('stars')),
            // Un `swimming_pool=yes` sans type ne permet pas de choisir entre
            // les deux entrées de la liste bien-être : on s'abstient.
            piscineInterieure: 'indoor' === $tag('swimming_pool') ? true : null,
            piscineExterieure: 'outdoor' === $tag('swimming_pool') ? true : null,
            sauna: self::triState($tag('sauna'), ['yes']),
            spa: self::triState($tag('spa'), ['yes']),
            jardin: 'garden' === $tag('leisure') ? true : self::triState($tag('garden'), ['yes']),
            // `parking` porte le type d'aire (surface, underground…) : toute
            // valeur autre que « no » signale une offre de stationnement.
            parking: null === $tag('parking') ? null : 'no' !== $tag('parking'),
            ascenseur: self::triState($tag('elevator'), ['yes']),
            chambres: self::chambres($tag('rooms')),
            horairesOuverture: self::premiereChaine($raw, ['opening_hours']),
            categorie: $tag('tourism') ?? $tag('amenity'),
        );
    }

    /** Nombre de chambres OSM `rooms` : entier plausible 1..2000, sinon null. */
    private static function chambres(?string $valeur): ?int
    {
        if (null === $valeur || 1 !== preg_match('/^\d{1,4}$/', $valeur)) {
            return null;
        }
        $nombre = (int) $valeur;

        return $nombre >= 1 && $nombre <= 2000 ? $nombre : null;
    }

    /** Classement OSM `stars` : « 4 » ou « 4S » (supérieur) → 4 ; hors 1..5 → null. */
    private static function etoiles(?string $valeur): ?int
    {
        if (null === $valeur || 1 !== preg_match('/^([1-5])s?$/', $valeur, $trouve)) {
            return null;
        }

        return (int) $trouve[1];
    }

    /**
     * @param array<string, mixed> $raw
     * @param list<string>         $cles
     */
    private static function premiereChaine(array $raw, array $cles): ?string
    {
        foreach ($cles as $cle) {
            if (is_string($raw[$cle] ?? null) && '' !== trim($raw[$cle])) {
                return trim($raw[$cle]);
            }
        }

        return null;
    }

    /** @param list<string> $vrais valeurs OSM comptant pour « oui » ; « no » vaut faux, le reste null (inconnu). */
    private static function triState(?string $valeur, array $vrais): ?bool
    {
        if (null === $valeur) {
            return null;
        }
        if (in_array($valeur, $vrais, true)) {
            return true;
        }

        return 'no' === $valeur ? false : null;
    }
}
