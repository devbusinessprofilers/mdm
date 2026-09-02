<?php

declare(strict_types=1);

namespace App\Pim\Service;

/**
 * Référentiel géographique français (libellés canoniques INSEE) : normalise
 * les régions et départements saisis librement (casse, accents, tirets —
 * « Île-De-France », « ile de france » → « Île-de-France ») et répare les
 * codes postaux amputés de leur zéro initial. Correspondance par clé de
 * repli EXACTE uniquement : une valeur inconnue (région étrangère,
 * lieu-dit) est toujours rendue telle quelle.
 */
final class ReferentielGeographiqueFrancais
{
    /** @var list<string> Libellés canoniques INSEE des 18 régions. */
    private const REGIONS = [
        'Auvergne-Rhône-Alpes',
        'Bourgogne-Franche-Comté',
        'Bretagne',
        'Centre-Val de Loire',
        'Corse',
        'Grand Est',
        'Hauts-de-France',
        'Île-de-France',
        'Normandie',
        'Nouvelle-Aquitaine',
        'Occitanie',
        'Pays de la Loire',
        "Provence-Alpes-Côte d'Azur",
        'Guadeloupe',
        'Martinique',
        'Guyane',
        'La Réunion',
        'Mayotte',
    ];

    /** @var array<int|string, string> Numéro INSEE → libellé canonique des 101 départements (PHP force « 10 »…« 95 » en int). */
    private const DEPARTEMENTS = [
        '01' => 'Ain', '02' => 'Aisne', '03' => 'Allier',
        '04' => 'Alpes-de-Haute-Provence', '05' => 'Hautes-Alpes',
        '06' => 'Alpes-Maritimes', '07' => 'Ardèche', '08' => 'Ardennes',
        '09' => 'Ariège', '10' => 'Aube', '11' => 'Aude', '12' => 'Aveyron',
        '13' => 'Bouches-du-Rhône', '14' => 'Calvados', '15' => 'Cantal',
        '16' => 'Charente', '17' => 'Charente-Maritime', '18' => 'Cher',
        '19' => 'Corrèze', '2A' => 'Corse-du-Sud', '2B' => 'Haute-Corse',
        '21' => "Côte-d'Or", '22' => "Côtes-d'Armor", '23' => 'Creuse',
        '24' => 'Dordogne', '25' => 'Doubs', '26' => 'Drôme', '27' => 'Eure',
        '28' => 'Eure-et-Loir', '29' => 'Finistère', '30' => 'Gard',
        '31' => 'Haute-Garonne', '32' => 'Gers', '33' => 'Gironde',
        '34' => 'Hérault', '35' => 'Ille-et-Vilaine', '36' => 'Indre',
        '37' => 'Indre-et-Loire', '38' => 'Isère', '39' => 'Jura',
        '40' => 'Landes', '41' => 'Loir-et-Cher', '42' => 'Loire',
        '43' => 'Haute-Loire', '44' => 'Loire-Atlantique', '45' => 'Loiret',
        '46' => 'Lot', '47' => 'Lot-et-Garonne', '48' => 'Lozère',
        '49' => 'Maine-et-Loire', '50' => 'Manche', '51' => 'Marne',
        '52' => 'Haute-Marne', '53' => 'Mayenne', '54' => 'Meurthe-et-Moselle',
        '55' => 'Meuse', '56' => 'Morbihan', '57' => 'Moselle',
        '58' => 'Nièvre', '59' => 'Nord', '60' => 'Oise', '61' => 'Orne',
        '62' => 'Pas-de-Calais', '63' => 'Puy-de-Dôme',
        '64' => 'Pyrénées-Atlantiques', '65' => 'Hautes-Pyrénées',
        '66' => 'Pyrénées-Orientales', '67' => 'Bas-Rhin', '68' => 'Haut-Rhin',
        '69' => 'Rhône', '70' => 'Haute-Saône', '71' => 'Saône-et-Loire',
        '72' => 'Sarthe', '73' => 'Savoie', '74' => 'Haute-Savoie',
        '75' => 'Paris', '76' => 'Seine-Maritime', '77' => 'Seine-et-Marne',
        '78' => 'Yvelines', '79' => 'Deux-Sèvres', '80' => 'Somme',
        '81' => 'Tarn', '82' => 'Tarn-et-Garonne', '83' => 'Var',
        '84' => 'Vaucluse', '85' => 'Vendée', '86' => 'Vienne',
        '87' => 'Haute-Vienne', '88' => 'Vosges', '89' => 'Yonne',
        '90' => 'Territoire de Belfort', '91' => 'Essonne',
        '92' => 'Hauts-de-Seine', '93' => 'Seine-Saint-Denis',
        '94' => 'Val-de-Marne', '95' => "Val-d'Oise",
        '971' => 'Guadeloupe', '972' => 'Martinique', '973' => 'Guyane',
        '974' => 'La Réunion', '976' => 'Mayotte',
    ];

    /** @var array<string, string>|null */
    private static ?array $regionsParCle = null;

    /** @var array<string, string>|null */
    private static ?array $departementsParCle = null;

    /** @var array<string, string>|null */
    private static ?array $numerosParDepartement = null;

    /** @return list<string> Libellés canoniques des régions, pour les listes de choix. */
    public static function regions(): array
    {
        return self::REGIONS;
    }

    /** @return list<string> Libellés canoniques des départements, pour les listes de choix. */
    public static function departements(): array
    {
        return array_values(self::DEPARTEMENTS);
    }

    /** Libellé canonique INSEE, ou la valeur d'origine si elle est inconnue du référentiel. */
    public static function normaliserRegion(string $region): string
    {
        return self::regionsParCle()[self::cle($region)] ?? $region;
    }

    /** Libellé canonique INSEE, ou la valeur d'origine si elle est inconnue du référentiel. */
    public static function normaliserDepartement(string $departement): string
    {
        return self::departementsParCle()[self::cle($departement)] ?? $departement;
    }

    /** Numéro INSEE (« 06 », « 2A », « 974 ») du département, par libellé souple. */
    public static function numeroDepartement(string $departement): ?string
    {
        return self::numerosParDepartement()[self::cle($departement)] ?? null;
    }

    /** Libellé canonique du département par son numéro INSEE (« 60 » → « Oise »). */
    public static function libelleDepartement(string $numero): ?string
    {
        return self::DEPARTEMENTS[strtoupper(trim($numero))] ?? null;
    }

    /**
     * Répare un code postal français amputé de son zéro initial par un
     * tableur (« 6130 » → « 06130 ») : uniquement 4 chiffres dont le zéro
     * restauré reste un code postal français plausible.
     */
    public static function reparerCodePostal(string $codePostal): string
    {
        if (1 === preg_match('/^[1-9]\d{3}$/', $codePostal)) {
            return '0'.$codePostal;
        }

        return $codePostal;
    }

    /**
     * Le code postal appartient-il au département ? Null quand le contrôle
     * est impossible (département hors référentiel, CP non français).
     */
    public static function codePostalCoherent(string $codePostal, string $departement): ?bool
    {
        $numero = self::numeroDepartement($departement);
        if (null === $numero || 1 !== preg_match('/^\d{5}$/', $codePostal)) {
            return null;
        }
        // Corse : les CP 20xxx couvrent 2A et 2B ; DROM sur trois chiffres.
        if ('2A' === $numero || '2B' === $numero) {
            return str_starts_with($codePostal, '20');
        }
        if (3 === strlen($numero)) {
            return str_starts_with($codePostal, $numero);
        }

        return str_starts_with($codePostal, $numero);
    }

    /**
     * Clé de repli : minuscules, sans accents, tirets/apostrophes/points
     * ramenés à l'espace, espaces réduits. « Île-De-France » et
     * « ile de france » partagent la même clé.
     */
    public static function cle(string $valeur): string
    {
        $valeur = str_replace(['-', '’', "'", '.'], ' ', $valeur);
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $valeur);

        return mb_strtolower(trim(preg_replace('/\s+/', ' ', false === $ascii ? $valeur : $ascii) ?? $valeur));
    }

    /** @return array<string, string> */
    private static function regionsParCle(): array
    {
        if (null === self::$regionsParCle) {
            self::$regionsParCle = [];
            foreach (self::REGIONS as $region) {
                self::$regionsParCle[self::cle($region)] = $region;
            }
        }

        return self::$regionsParCle;
    }

    /** @return array<string, string> */
    private static function departementsParCle(): array
    {
        if (null === self::$departementsParCle) {
            self::$departementsParCle = [];
            foreach (self::DEPARTEMENTS as $libelle) {
                self::$departementsParCle[self::cle($libelle)] = $libelle;
            }
        }

        return self::$departementsParCle;
    }

    /** @return array<string, string> */
    private static function numerosParDepartement(): array
    {
        if (null === self::$numerosParDepartement) {
            self::$numerosParDepartement = [];
            foreach (self::DEPARTEMENTS as $numero => $libelle) {
                self::$numerosParDepartement[self::cle($libelle)] = (string) $numero;
            }
        }

        return self::$numerosParDepartement;
    }
}
