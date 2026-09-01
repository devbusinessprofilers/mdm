<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Enum\SuggestionAction;
use App\Pim\Lov\RestaurantLovCatalog;

/**
 * Confronte un restaurant à Geoapify Place Details (tags OpenStreetMap) pour
 * proposer des attributs manquants, sans rien persister : type de cuisine,
 * spécificités alimentaires, équipements (wifi, terrasse, climatisation),
 * accessibilité PMR, site officiel. On ne propose qu'un enrichissement (valeurs
 * absentes) : jamais d'écrasement de la saisie. Fonctionne dans tous les pays
 * (OSM est mondial) dès qu'un GPS est présent sur la fiche.
 */
final readonly class RestaurantAttributsVerifier
{
    /** OSM `cuisine` → code LOV TYPE_CUISINE (sélection à haute confiance). */
    private const CUISINE = [
        'french' => 'FRANCAIS', 'italian' => 'ITALIEN', 'pizza' => 'ITALIEN', 'japanese' => 'JAPONAIS',
        'sushi' => 'JAPONAIS', 'chinese' => 'CHINOIS', 'indian' => 'INDIEN', 'thai' => 'THAILANDAIS',
        'vietnamese' => 'VIETNAMIEN', 'korean' => 'COREEN', 'mexican' => 'MEXICAIN', 'spanish' => 'ESPAGNOL',
        'portuguese' => 'PORTUGAIS', 'lebanese' => 'LIBANAIS', 'greek' => 'GREC', 'moroccan' => 'MAROCAIN',
        'tunisian' => 'TUNISIEN', 'algerian' => 'ALGERIEN', 'turkish' => 'TURC', 'german' => 'ALLEMAND',
        'american' => 'AMERICAIN', 'argentinian' => 'ARGENTIN', 'brazilian' => 'BRESILIEN', 'peruvian' => 'PERUVIEN',
        'russian' => 'RUSSE', 'mediterranean' => 'MEDITERRANEEN', 'seafood' => 'FRUITS_DE_MER', 'fish' => 'FRUITS_DE_MER',
        'steak_house' => 'STEAKHOUSE', 'crepe' => 'CREPERIE', 'creole' => 'CREOLE', 'savoyard' => 'SAVOYARD',
        'basque' => 'BASQUE', 'corsican' => 'CORSE', 'vegan' => 'VEGAN', 'vegetarian' => 'VEGETARIEN',
        'lyonnais' => 'BOUCHON_LYONNAIS', 'asian' => 'ASIATIQUE', 'african' => 'AFRICAIN', 'afghan' => 'AFGHAN',
        'armenian' => 'ARMENIEN', 'cambodian' => 'CAMBODGIEN', 'egyptian' => 'EGYPTIEN', 'ethiopian' => 'ETHIOPIEN',
        'pakistani' => 'PAKISTANAIS', 'syrian' => 'SYRIEN', 'iranian' => 'IRANIEN', 'regional' => 'TRADITIONNELLE',
        'french_traditional' => 'TRADITIONNELLE',
    ];

    /** Régime OSM → code LOV SPECIFICITE_ALIMENTAIRE. */
    private const REGIME = [
        'vegan' => 'VEGAN', 'vegetarian' => 'VEGETARIENNES', 'halal' => 'HALAL', 'kosher' => 'CASHER', 'organic' => 'PLATS_BIO',
    ];

    public function __construct(private GeoapifyClient $geoapify)
    {
    }

    /**
     * @return list<SuggestionProposee>
     *
     * @throws EnrichissementIndisponibleException quand Geoapify est en panne
     *                                             ou sous quota
     */
    public function analyser(Restaurant $restaurant): array
    {
        $localisation = $restaurant->localisation();
        if (null === $localisation || null === $localisation->latitude() || null === $localisation->longitude()) {
            return [];
        }
        // Le nom de la fiche sert de contre-vérification : sans lui, un GPS
        // imprécis ferait remonter les attributs du commerce voisin.
        $nom = trim((string) $restaurant->fiche()->label());
        $attributs = $this->geoapify->detailsPlace($localisation->latitude(), $localisation->longitude(), '' === $nom ? null : $nom);
        if (null === $attributs || $attributs->estVide()) {
            return [];
        }
        $propositions = [];

        $this->ajouterLov($propositions, 'restaurant_types_cuisine', 'TYPE_CUISINE', 'Type de cuisine',
            self::mapper($attributs->cuisines, self::CUISINE), $restaurant->typesCuisine());
        $this->ajouterLov($propositions, 'restaurant_specificites', 'SPECIFICITE_ALIMENTAIRE', 'Spécificités alimentaires',
            self::mapper($attributs->regimes, self::REGIME), $restaurant->specificitesAlimentaires());
        $this->ajouterLov($propositions, 'restaurant_equipements', 'EQUIPEMENT_RESTAURANT', 'Équipements',
            self::equipements($attributs), $restaurant->equipements());

        $this->ajouterBool($propositions, 'restaurant_acces_pmr', 'Accès PMR', $attributs->accesPmr, $restaurant->accesPmr());
        $this->ajouterBool($propositions, 'restaurant_toilettes_pmr', 'Toilettes PMR', $attributs->toilettesPmr, $restaurant->toilettesPmr());

        // Horaires OSM : jours en union ; l'amplitude (deux champs globaux
        // seulement) n'est proposée que si elle est uniforme sur la semaine.
        $horairesOsm = null === $attributs->horairesOuverture ? null : HorairesOsm::parser($attributs->horairesOuverture);
        if (null !== $horairesOsm) {
            $this->ajouterLov($propositions, 'restaurant_lov_jours_ouverture', 'DISPO_JOUR_OUVERTURE', 'Jours d\'ouverture',
                $horairesOsm['jours'], $restaurant->joursOuverture());
            $plages = array_values(array_unique(array_map(
                static fn (array $heures): string => $heures['ouverture'].'-'.$heures['fermeture'],
                $horairesOsm['horaires'],
            )));
            if (1 === count($plages) && null === $restaurant->heureOuverture() && null === $restaurant->heureFermeture()) {
                [$ouverture, $fermeture] = explode('-', $plages[0]);
                $propositions[] = new SuggestionProposee(
                    action: SuggestionAction::RemplirChamp,
                    champ: 'restaurant_horaires',
                    label: 'Horaires d\'ouverture',
                    valeurActuelle: null,
                    valeurProposee: sprintf('%s – %s', $ouverture, $fermeture),
                    score: null,
                    payload: ['ouverture' => $ouverture, 'fermeture' => $fermeture],
                );
            }
        }

        if (null !== $attributs->siteWeb
            && null === $restaurant->siteOfficiel()
            && mb_strlen($attributs->siteWeb) <= Restaurant::WEBSITE_MAX_LENGTH) {
            $propositions[] = new SuggestionProposee(
                action: SuggestionAction::RemplirChamp,
                champ: 'restaurant_site_officiel',
                label: 'Site officiel',
                valeurActuelle: null,
                valeurProposee: $attributs->siteWeb,
                score: null,
            );
        }
        return $propositions;
    }

    /**
     * @param list<SuggestionProposee> $propositions
     * @param list<string>             $codesProposes codes LOV issus d'OSM
     * @param list<string>             $codesActuels  codes LOV déjà sur la fiche
     */
    private function ajouterLov(array &$propositions, string $champ, string $attribut, string $label, array $codesProposes, array $codesActuels): void
    {
        // Normalisation vers le référentiel effectif (runtime d'abord) : un
        // candidat d'un autre schéma de codes ou en forme libellé est résolu
        // par comparaison de libellés, un candidat non résolu est écarté — on
        // ne propose jamais un code inconnu de la liste réelle.
        $valeurs = RestaurantLovCatalog::values($attribut);
        $resolus = [];
        foreach ($codesProposes as $candidat) {
            $code = LovValeurResolution::codePour($valeurs, $candidat);
            if (null !== $code) {
                $resolus[] = $code;
            }
        }
        $delta = array_values(array_diff(array_unique($resolus), $codesActuels));
        if ([] === $delta) {
            return;
        }
        $propositions[] = new SuggestionProposee(
            action: SuggestionAction::RemplirChamp,
            champ: $champ,
            label: $label,
            valeurActuelle: '' === self::libelles($attribut, $codesActuels) ? null : self::libelles($attribut, $codesActuels),
            valeurProposee: self::libelles($attribut, $delta),
            score: null,
            payload: ['codes' => $delta],
        );
    }

    /**
     * @param list<SuggestionProposee> $propositions
     */
    private function ajouterBool(array &$propositions, string $champ, string $label, ?bool $propose, ?bool $actuel): void
    {
        if (null === $propose || null !== $actuel) {
            return;
        }
        $propositions[] = new SuggestionProposee(
            action: SuggestionAction::RemplirChamp,
            champ: $champ,
            label: $label,
            valeurActuelle: null,
            valeurProposee: $propose ? 'Oui' : 'Non',
            score: null,
            payload: ['bool' => $propose],
        );
    }

    /**
     * @param list<string>          $valeurs
     * @param array<string, string> $table
     *
     * @return list<string>
     */
    private static function mapper(array $valeurs, array $table): array
    {
        $codes = [];
        foreach ($valeurs as $valeur) {
            if (isset($table[$valeur])) {
                $codes[] = $table[$valeur];
            }
        }

        return $codes;
    }

    /** @return list<string> */
    private static function equipements(PlaceAttributs $attributs): array
    {
        $codes = [];
        if (true === $attributs->wifi) { $codes[] = 'WIFI'; }
        if (true === $attributs->terrasse) { $codes[] = 'TERRASSE'; }
        if (true === $attributs->climatisation) { $codes[] = 'CLIMATISATION'; }

        return $codes;
    }

    /** @param list<string> $codes */
    private static function libelles(string $attribut, array $codes): string
    {
        $valeurs = RestaurantLovCatalog::values($attribut);

        return implode(', ', array_map(static fn (string $code): string => $valeurs[$code] ?? $code, $codes));
    }
}
