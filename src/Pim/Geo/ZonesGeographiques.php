<?php

declare(strict_types=1);

namespace App\Pim\Geo;

use Symfony\Component\Intl\Countries;

/**
 * Référentiel statique des zones d'intervention mobiles (maquette portail :
 * pays → régions → départements « ou équivalent »). Les pays viennent de
 * l'ICU (libellés français, codes ISO 3166-1 alpha-2) ; régions et
 * départements sont codés « PAYS-CODE » (ISO 3166-2 quand il existe). La
 * France est complète (18 régions, 101 départements) ; les pays voisins
 * portent leurs régions (Länder, cantons, provinces…) et, pour la Belgique,
 * leurs provinces. Les autres pays n'ont pas de subdivision : leurs sélecteurs
 * restent vides.
 *
 * Les libellés historiques saisis en texte libre (« Île-de-France »,
 * « Provence-Alpes-Côte-D’azur », « Yvelines ») se résolvent en code par
 * resoudre*() — utilisé par la migration de reprise et par les formulaires.
 */
final class ZonesGeographiques
{
    /** @var array<string, array{0: string, 1: string}> code région => [pays, libellé] */
    private const REGIONS = [
        'FR-ARA' => ['FR', 'Auvergne-Rhône-Alpes'], 'FR-BFC' => ['FR', 'Bourgogne-Franche-Comté'], 'FR-BRE' => ['FR', 'Bretagne'],
        'FR-CVL' => ['FR', 'Centre-Val de Loire'], 'FR-20R' => ['FR', 'Corse'], 'FR-GES' => ['FR', 'Grand Est'],
        'FR-HDF' => ['FR', 'Hauts-de-France'], 'FR-IDF' => ['FR', 'Île-de-France'], 'FR-NOR' => ['FR', 'Normandie'],
        'FR-NAQ' => ['FR', 'Nouvelle-Aquitaine'], 'FR-OCC' => ['FR', 'Occitanie'], 'FR-PDL' => ['FR', 'Pays de la Loire'],
        'FR-PAC' => ['FR', 'Provence-Alpes-Côte d’Azur'], 'FR-971' => ['FR', 'Guadeloupe'], 'FR-972' => ['FR', 'Martinique'],
        'FR-973' => ['FR', 'Guyane'], 'FR-974' => ['FR', 'La Réunion'], 'FR-976' => ['FR', 'Mayotte'],
        'BE-BRU' => ['BE', 'Région de Bruxelles-Capitale'], 'BE-VLG' => ['BE', 'Région flamande'], 'BE-WAL' => ['BE', 'Région wallonne'],
        'CH-AG' => ['CH', 'Argovie'], 'CH-AI' => ['CH', 'Appenzell Rhodes-Intérieures'], 'CH-AR' => ['CH', 'Appenzell Rhodes-Extérieures'],
        'CH-BE' => ['CH', 'Berne'], 'CH-BL' => ['CH', 'Bâle-Campagne'], 'CH-BS' => ['CH', 'Bâle-Ville'], 'CH-FR' => ['CH', 'Fribourg'],
        'CH-GE' => ['CH', 'Genève'], 'CH-GL' => ['CH', 'Glaris'], 'CH-GR' => ['CH', 'Grisons'], 'CH-JU' => ['CH', 'Jura'],
        'CH-LU' => ['CH', 'Lucerne'], 'CH-NE' => ['CH', 'Neuchâtel'], 'CH-NW' => ['CH', 'Nidwald'], 'CH-OW' => ['CH', 'Obwald'],
        'CH-SG' => ['CH', 'Saint-Gall'], 'CH-SH' => ['CH', 'Schaffhouse'], 'CH-SO' => ['CH', 'Soleure'], 'CH-SZ' => ['CH', 'Schwytz'],
        'CH-TG' => ['CH', 'Thurgovie'], 'CH-TI' => ['CH', 'Tessin'], 'CH-UR' => ['CH', 'Uri'], 'CH-VD' => ['CH', 'Vaud'],
        'CH-VS' => ['CH', 'Valais'], 'CH-ZG' => ['CH', 'Zoug'], 'CH-ZH' => ['CH', 'Zurich'],
        'LU-CA' => ['LU', 'Capellen'], 'LU-CL' => ['LU', 'Clervaux'], 'LU-DI' => ['LU', 'Diekirch'], 'LU-EC' => ['LU', 'Echternach'],
        'LU-ES' => ['LU', 'Esch-sur-Alzette'], 'LU-GR' => ['LU', 'Grevenmacher'], 'LU-LU' => ['LU', 'Luxembourg'], 'LU-ME' => ['LU', 'Mersch'],
        'LU-RD' => ['LU', 'Redange'], 'LU-RM' => ['LU', 'Remich'], 'LU-VD' => ['LU', 'Vianden'], 'LU-WI' => ['LU', 'Wiltz'],
        'DE-BW' => ['DE', 'Bade-Wurtemberg'], 'DE-BY' => ['DE', 'Bavière'], 'DE-BE' => ['DE', 'Berlin'], 'DE-BB' => ['DE', 'Brandebourg'],
        'DE-HB' => ['DE', 'Brême'], 'DE-HH' => ['DE', 'Hambourg'], 'DE-HE' => ['DE', 'Hesse'], 'DE-MV' => ['DE', 'Mecklembourg-Poméranie-Occidentale'],
        'DE-NI' => ['DE', 'Basse-Saxe'], 'DE-NW' => ['DE', 'Rhénanie-du-Nord-Westphalie'], 'DE-RP' => ['DE', 'Rhénanie-Palatinat'],
        'DE-SL' => ['DE', 'Sarre'], 'DE-SN' => ['DE', 'Saxe'], 'DE-ST' => ['DE', 'Saxe-Anhalt'], 'DE-SH' => ['DE', 'Schleswig-Holstein'], 'DE-TH' => ['DE', 'Thuringe'],
        'ES-AN' => ['ES', 'Andalousie'], 'ES-AR' => ['ES', 'Aragon'], 'ES-AS' => ['ES', 'Asturies'], 'ES-IB' => ['ES', 'Îles Baléares'],
        'ES-CN' => ['ES', 'Îles Canaries'], 'ES-CB' => ['ES', 'Cantabrie'], 'ES-CL' => ['ES', 'Castille-et-León'], 'ES-CM' => ['ES', 'Castille-La Manche'],
        'ES-CT' => ['ES', 'Catalogne'], 'ES-EX' => ['ES', 'Estrémadure'], 'ES-GA' => ['ES', 'Galice'], 'ES-MD' => ['ES', 'Communauté de Madrid'],
        'ES-MC' => ['ES', 'Région de Murcie'], 'ES-NC' => ['ES', 'Navarre'], 'ES-PV' => ['ES', 'Pays basque'], 'ES-RI' => ['ES', 'La Rioja'],
        'ES-VC' => ['ES', 'Communauté valencienne'],
        'IT-65' => ['IT', 'Abruzzes'], 'IT-77' => ['IT', 'Basilicate'], 'IT-78' => ['IT', 'Calabre'], 'IT-72' => ['IT', 'Campanie'],
        'IT-45' => ['IT', 'Émilie-Romagne'], 'IT-36' => ['IT', 'Frioul-Vénétie Julienne'], 'IT-62' => ['IT', 'Latium'], 'IT-42' => ['IT', 'Ligurie'],
        'IT-25' => ['IT', 'Lombardie'], 'IT-57' => ['IT', 'Marches'], 'IT-67' => ['IT', 'Molise'], 'IT-21' => ['IT', 'Piémont'],
        'IT-75' => ['IT', 'Pouilles'], 'IT-88' => ['IT', 'Sardaigne'], 'IT-82' => ['IT', 'Sicile'], 'IT-52' => ['IT', 'Toscane'],
        'IT-32' => ['IT', 'Trentin-Haut-Adige'], 'IT-55' => ['IT', 'Ombrie'], 'IT-23' => ['IT', 'Vallée d’Aoste'], 'IT-34' => ['IT', 'Vénétie'],
        'NL-DR' => ['NL', 'Drenthe'], 'NL-FL' => ['NL', 'Flevoland'], 'NL-FR' => ['NL', 'Frise'], 'NL-GE' => ['NL', 'Gueldre'],
        'NL-GR' => ['NL', 'Groningue'], 'NL-LI' => ['NL', 'Limbourg'], 'NL-NB' => ['NL', 'Brabant-Septentrional'], 'NL-NH' => ['NL', 'Hollande-Septentrionale'],
        'NL-OV' => ['NL', 'Overijssel'], 'NL-UT' => ['NL', 'Utrecht'], 'NL-ZE' => ['NL', 'Zélande'], 'NL-ZH' => ['NL', 'Hollande-Méridionale'],
        'PT-N' => ['PT', 'Nord'], 'PT-C' => ['PT', 'Centre'], 'PT-L' => ['PT', 'Lisbonne'], 'PT-A' => ['PT', 'Alentejo'],
        'PT-AL' => ['PT', 'Algarve'], 'PT-20' => ['PT', 'Açores'], 'PT-30' => ['PT', 'Madère'],
        'GB-ENG' => ['GB', 'Angleterre'], 'GB-SCT' => ['GB', 'Écosse'], 'GB-WLS' => ['GB', 'Pays de Galles'], 'GB-NIR' => ['GB', 'Irlande du Nord'],
    ];

    /** @var array<string, array{0: string, 1: string}> code département => [région, libellé] */
    private const DEPARTEMENTS = [
        'FR-01' => ['FR-ARA', 'Ain'], 'FR-03' => ['FR-ARA', 'Allier'], 'FR-07' => ['FR-ARA', 'Ardèche'], 'FR-15' => ['FR-ARA', 'Cantal'],
        'FR-26' => ['FR-ARA', 'Drôme'], 'FR-38' => ['FR-ARA', 'Isère'], 'FR-42' => ['FR-ARA', 'Loire'], 'FR-43' => ['FR-ARA', 'Haute-Loire'],
        'FR-63' => ['FR-ARA', 'Puy-de-Dôme'], 'FR-69' => ['FR-ARA', 'Rhône'], 'FR-73' => ['FR-ARA', 'Savoie'], 'FR-74' => ['FR-ARA', 'Haute-Savoie'],
        'FR-21' => ['FR-BFC', 'Côte-d’Or'], 'FR-25' => ['FR-BFC', 'Doubs'], 'FR-39' => ['FR-BFC', 'Jura'], 'FR-58' => ['FR-BFC', 'Nièvre'],
        'FR-70' => ['FR-BFC', 'Haute-Saône'], 'FR-71' => ['FR-BFC', 'Saône-et-Loire'], 'FR-89' => ['FR-BFC', 'Yonne'], 'FR-90' => ['FR-BFC', 'Territoire de Belfort'],
        'FR-22' => ['FR-BRE', 'Côtes-d’Armor'], 'FR-29' => ['FR-BRE', 'Finistère'], 'FR-35' => ['FR-BRE', 'Ille-et-Vilaine'], 'FR-56' => ['FR-BRE', 'Morbihan'],
        'FR-18' => ['FR-CVL', 'Cher'], 'FR-28' => ['FR-CVL', 'Eure-et-Loir'], 'FR-36' => ['FR-CVL', 'Indre'], 'FR-37' => ['FR-CVL', 'Indre-et-Loire'],
        'FR-41' => ['FR-CVL', 'Loir-et-Cher'], 'FR-45' => ['FR-CVL', 'Loiret'],
        'FR-2A' => ['FR-20R', 'Corse-du-Sud'], 'FR-2B' => ['FR-20R', 'Haute-Corse'],
        'FR-08' => ['FR-GES', 'Ardennes'], 'FR-10' => ['FR-GES', 'Aube'], 'FR-51' => ['FR-GES', 'Marne'], 'FR-52' => ['FR-GES', 'Haute-Marne'],
        'FR-54' => ['FR-GES', 'Meurthe-et-Moselle'], 'FR-55' => ['FR-GES', 'Meuse'], 'FR-57' => ['FR-GES', 'Moselle'], 'FR-67' => ['FR-GES', 'Bas-Rhin'],
        'FR-68' => ['FR-GES', 'Haut-Rhin'], 'FR-88' => ['FR-GES', 'Vosges'],
        'FR-02' => ['FR-HDF', 'Aisne'], 'FR-59' => ['FR-HDF', 'Nord'], 'FR-60' => ['FR-HDF', 'Oise'], 'FR-62' => ['FR-HDF', 'Pas-de-Calais'], 'FR-80' => ['FR-HDF', 'Somme'],
        'FR-75' => ['FR-IDF', 'Paris'], 'FR-77' => ['FR-IDF', 'Seine-et-Marne'], 'FR-78' => ['FR-IDF', 'Yvelines'], 'FR-91' => ['FR-IDF', 'Essonne'],
        'FR-92' => ['FR-IDF', 'Hauts-de-Seine'], 'FR-93' => ['FR-IDF', 'Seine-Saint-Denis'], 'FR-94' => ['FR-IDF', 'Val-de-Marne'], 'FR-95' => ['FR-IDF', 'Val-d’Oise'],
        'FR-14' => ['FR-NOR', 'Calvados'], 'FR-27' => ['FR-NOR', 'Eure'], 'FR-50' => ['FR-NOR', 'Manche'], 'FR-61' => ['FR-NOR', 'Orne'], 'FR-76' => ['FR-NOR', 'Seine-Maritime'],
        'FR-16' => ['FR-NAQ', 'Charente'], 'FR-17' => ['FR-NAQ', 'Charente-Maritime'], 'FR-19' => ['FR-NAQ', 'Corrèze'], 'FR-23' => ['FR-NAQ', 'Creuse'],
        'FR-24' => ['FR-NAQ', 'Dordogne'], 'FR-33' => ['FR-NAQ', 'Gironde'], 'FR-40' => ['FR-NAQ', 'Landes'], 'FR-47' => ['FR-NAQ', 'Lot-et-Garonne'],
        'FR-64' => ['FR-NAQ', 'Pyrénées-Atlantiques'], 'FR-79' => ['FR-NAQ', 'Deux-Sèvres'], 'FR-86' => ['FR-NAQ', 'Vienne'], 'FR-87' => ['FR-NAQ', 'Haute-Vienne'],
        'FR-09' => ['FR-OCC', 'Ariège'], 'FR-11' => ['FR-OCC', 'Aude'], 'FR-12' => ['FR-OCC', 'Aveyron'], 'FR-30' => ['FR-OCC', 'Gard'],
        'FR-31' => ['FR-OCC', 'Haute-Garonne'], 'FR-32' => ['FR-OCC', 'Gers'], 'FR-34' => ['FR-OCC', 'Hérault'], 'FR-46' => ['FR-OCC', 'Lot'],
        'FR-48' => ['FR-OCC', 'Lozère'], 'FR-65' => ['FR-OCC', 'Hautes-Pyrénées'], 'FR-66' => ['FR-OCC', 'Pyrénées-Orientales'], 'FR-81' => ['FR-OCC', 'Tarn'],
        'FR-82' => ['FR-OCC', 'Tarn-et-Garonne'],
        'FR-44' => ['FR-PDL', 'Loire-Atlantique'], 'FR-49' => ['FR-PDL', 'Maine-et-Loire'], 'FR-53' => ['FR-PDL', 'Mayenne'], 'FR-72' => ['FR-PDL', 'Sarthe'], 'FR-85' => ['FR-PDL', 'Vendée'],
        'FR-04' => ['FR-PAC', 'Alpes-de-Haute-Provence'], 'FR-05' => ['FR-PAC', 'Hautes-Alpes'], 'FR-06' => ['FR-PAC', 'Alpes-Maritimes'],
        'FR-13' => ['FR-PAC', 'Bouches-du-Rhône'], 'FR-83' => ['FR-PAC', 'Var'], 'FR-84' => ['FR-PAC', 'Vaucluse'],
        'FR-971' => ['FR-971', 'Guadeloupe'], 'FR-972' => ['FR-972', 'Martinique'], 'FR-973' => ['FR-973', 'Guyane'], 'FR-974' => ['FR-974', 'La Réunion'], 'FR-976' => ['FR-976', 'Mayotte'],
        'BE-VAN' => ['BE-VLG', 'Anvers'], 'BE-VBR' => ['BE-VLG', 'Brabant flamand'], 'BE-VOV' => ['BE-VLG', 'Flandre-Orientale'], 'BE-VWV' => ['BE-VLG', 'Flandre-Occidentale'],
        'BE-VLI' => ['BE-VLG', 'Limbourg'], 'BE-WBR' => ['BE-WAL', 'Brabant wallon'], 'BE-WHT' => ['BE-WAL', 'Hainaut'], 'BE-WLG' => ['BE-WAL', 'Liège'],
        'BE-WLX' => ['BE-WAL', 'Luxembourg'], 'BE-WNA' => ['BE-WAL', 'Namur'], 'BE-BRU' => ['BE-BRU', 'Bruxelles-Capitale'],
    ];

    /** @var array<string, string> variantes historiques (texte libre) => code */
    private const ALIAS = [
        'centre val de loire' => 'FR-CVL', 'grand est' => 'FR-GES', 'pays de la loire' => 'FR-PDL', 'provence alpes cote d azur' => 'FR-PAC',
        'provence alpes cote dazur' => 'FR-PAC', 'paca' => 'FR-PAC', 'ile de france' => 'FR-IDF', 'idf' => 'FR-IDF', 'corse' => 'FR-20R',
        'reunion' => 'FR-974', 'alpes de haute provence' => 'FR-04', 'cotes d armor' => 'FR-22', 'cotes darmor' => 'FR-22', 'cote d or' => 'FR-21', 'cote dor' => 'FR-21',
        'val d oise' => 'FR-95', 'val doise' => 'FR-95', 'territoire de belfort' => 'FR-90',
    ];

    /** @return array<string, string> libellé => code ISO, triés par libellé */
    public static function pays(): array
    {
        $choix = array_flip(Countries::getNames('fr'));
        ksort($choix, SORT_LOCALE_STRING);

        return $choix;
    }

    /** @return array<string, string> libellé => code, toutes régions (préfixées du pays hors France) */
    public static function regions(): array
    {
        $choix = [];
        foreach (self::REGIONS as $code => [$pays, $libelle]) {
            $choix['FR' === $pays ? $libelle : sprintf('%s (%s)', $libelle, self::libellePays($pays))] = $code;
        }

        return $choix;
    }

    /** @return array<string, string> libellé => code, tous départements (préfixés du pays hors France) */
    public static function departements(): array
    {
        $choix = [];
        foreach (self::DEPARTEMENTS as $code => [$region, $libelle]) {
            $pays = self::REGIONS[$region][0];
            $choix['FR' === $pays ? sprintf('%s %s', substr($code, 3), $libelle) : sprintf('%s (%s)', $libelle, self::libellePays($pays))] = $code;
        }

        return $choix;
    }

    /** @return array<string, string> code région => code pays */
    public static function paysDesRegions(): array
    {
        return array_map(static fn (array $r): string => $r[0], self::REGIONS);
    }

    /** @return array<string, string> code département => code région */
    public static function regionsDesDepartements(): array
    {
        return array_map(static fn (array $d): string => $d[0], self::DEPARTEMENTS);
    }

    /**
     * Attributs Stimulus de la carte des zones mobiles : le contrôleur
     * zones-geo restreint régions puis départements aux pays / régions cochés.
     *
     * @return array<string, string>
     */
    public static function attributsStimulus(): array
    {
        return [
            'data-controller' => 'zones-geo',
            'data-action' => 'select:change->zones-geo#maj change->zones-geo#maj',
            'data-zones-geo-regions-value' => (string) json_encode(self::paysDesRegions(), JSON_THROW_ON_ERROR),
            'data-zones-geo-departements-value' => (string) json_encode(self::regionsDesDepartements(), JSON_THROW_ON_ERROR),
        ];
    }

    public static function resoudrePays(string $valeur): ?string
    {
        $valeur = trim($valeur);
        if (2 === strlen($valeur) && Countries::exists(strtoupper($valeur))) {
            return strtoupper($valeur);
        }
        $cle = self::normaliser($valeur);
        foreach (Countries::getNames('fr') as $code => $libelle) {
            if (self::normaliser($libelle) === $cle) {
                return $code;
            }
        }

        return null;
    }

    public static function resoudreRegion(string $valeur): ?string
    {
        return self::resoudre($valeur, self::REGIONS);
    }

    public static function resoudreDepartement(string $valeur): ?string
    {
        return self::resoudre($valeur, self::DEPARTEMENTS);
    }

    /** @param array<string, array{0: string, 1: string}> $table */
    private static function resoudre(string $valeur, array $table): ?string
    {
        $valeur = trim($valeur);
        if (isset($table[strtoupper($valeur)])) {
            return strtoupper($valeur);
        }
        $cle = self::normaliser($valeur);
        if (isset(self::ALIAS[$cle]) && isset($table[self::ALIAS[$cle]])) {
            return self::ALIAS[$cle];
        }
        foreach ($table as $code => [, $libelle]) {
            if (self::normaliser($libelle) === $cle) {
                return $code;
            }
        }
        // « 75 Paris », « 2A », « 75 » : le numéro de département français.
        if (1 === preg_match('/^(\d{2,3}|2[AB])\b/i', $valeur, $m) && isset($table['FR-'.strtoupper($m[1])])) {
            return 'FR-'.strtoupper($m[1]);
        }

        return null;
    }

    private static function normaliser(string $valeur): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $valeur);
        $ascii = strtolower(false === $ascii ? $valeur : $ascii);
        $ascii = str_replace(['’', "'"], ' ', $ascii);

        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $ascii));
    }

    private static function libellePays(string $code): string
    {
        return Countries::getName($code, 'fr');
    }
}
