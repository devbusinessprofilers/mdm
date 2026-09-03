<?php

declare(strict_types=1);

namespace App\Pim\Lov;

final class RestaurantLovCatalog
{
    /** @var array<string, array<string, string>> */
    private const VALUES = [
        'TYPE_RESTAURANT' => [
            'BAR_A_VIN' => 'Bar à vin',
            'BISTRONOMIQUE' => 'Bistronomique',
            'BRASSERIE_BISTRO' => 'Brasserie/bistro',
            'CABARET' => 'Cabaret',
            'CAFE_BAR' => 'Café / Bar',
            'ETOILE' => 'Etoilé',
            'GASTRONOMIQUE' => 'Gastronomique',
            'BORD_EAU' => "Au bord de l'eau",
            'AU_VERT' => 'Au vert',
            'BORD_DE_MER' => 'Bord de mer',
            'BRANCHE' => 'Branché',
            'BUFFET_VOLONTE' => 'Buffet à volonté',
            'COSY' => 'Cosy',
            'ESTAMINET' => 'Estaminet',
            'FESTIF' => 'Festif',
            'IMMERSIF' => 'Immersif',
            'RESTAURANT_ALTITUDE' => "Restaurant d'altitude",
            'RESTAURANT_HOTEL' => "Restaurant d'hôtel",
            'PENICHE' => 'Sur une péniche',
            'TRATTORIA' => 'Trattoria',
            'DINER_SPECTACLE' => 'Dîner spectacle',
            'INSOLITE' => 'Insolite',
            'ROOFTOP' => 'Rooftop / Vue panoramique',
        ],
        'TYPE_CUISINE' => [
            'AFGHAN' => 'Afghan',
            'AFRICAIN' => 'Africain',
            'AFTERNOON_TEA' => 'Afternoon Tea',
            'ALGERIEN' => 'Algérien',
            'ALLEMAND' => 'Allemand',
            'ALSACIEN' => 'Alsacien',
            'AMERICAIN' => 'Américain',
            'ANGLAIS' => 'Anglais',
            'ARGENTIN' => 'Argentin',
            'ARMENIEN' => 'Arménien',
            'ASIATIQUE' => 'Asiatique',
            'AUVERGNAT' => 'Auvergnat',
            'BASQUE' => 'Basque',
            'BOUCHON_LYONNAIS' => 'Bouchon lyonnais',
            'BRESILIEN' => 'Brésilien',
            'CAMBODGIEN' => 'Cambodgien',
            'CANADIEN' => 'Canadien',
            'CHINOIS' => 'Chinois',
            'COLOMBIEN' => 'Colombien',
            'COREEN' => 'Coréen',
            'CORSE' => 'Corse',
            'CREOLE' => 'Créole',
            'CREPERIE' => 'Crêperie',
            'CUBAIN' => 'Cubain',
            'CUISINE_ILES' => 'Cuisine des îles',
            'CUISINE_MONDE' => 'Cuisine du monde',
            'TRADITIONNELLE' => 'Cuisine traditionnelle',
            'CONTEMPORAINE' => 'Cuisine contemporaine',
            'EGYPTIEN' => 'Egyptien',
            'ESPAGNOL' => 'Espagnol',
            'ETHIOPIEN' => 'Ethiopien',
            'EUROPE_EST' => "Europe de l'Est",
            'FRANCAIS' => 'Français',
            'FRANCO_BELGE' => 'Franco-belge',
            'FRUITS_DE_MER' => 'Fruits de mer',
            'FUSION' => 'Fusion',
            'GREC' => 'Grec',
            'HAWAIEN' => 'Hawaien',
            'INDIEN' => 'Indien',
            'IRANIEN' => 'Iranien',
            'ISRAELIEN' => 'Israelien',
            'ITALIEN' => 'Italien',
            'JAPONAIS' => 'Japonais',
            'LATINO' => 'Latino',
            'LIBANAIS' => 'Libanais',
            'MAROCAIN' => 'Marocain',
            'MEDITERRANEEN' => 'Méditérranéen',
            'MEXICAIN' => 'Mexicain',
            'ORIENTAL' => 'Oriental',
            'PAKISTANAIS' => 'Pakistanais',
            'PERUVIEN' => 'Péruvien',
            'PORTUGAIS' => 'Portugais',
            'PROVENCAL' => 'Provençal',
            'RUSSE' => 'Russe',
            'SAVOYARD' => 'Savoyard',
            'SCANDINAVE' => 'Scandinave',
            'STEAKHOUSE' => 'Steakhouse',
            'SUD_OUEST' => 'Sud-Ouest',
            'SYRIEN' => 'Syrien',
            'THAILANDAIS' => 'Thaïlandais',
            'TUNISIEN' => 'Tunisien',
            'TURC' => 'Turc',
            'VEGAN' => 'Vegan',
            'VEGETARIEN' => 'Végétarien',
            'VENEZUELIEN' => 'Vénézuelien',
            'VIETNAMIEN' => 'Vietnamien',
        ],
        'SPECIFICITE_ALIMENTAIRE' => [
            'CASHER' => 'Casher',
            'HALAL' => 'Halal',
            'VEGAN' => 'Vegan',
            'VEGETARIENNES' => 'Végétariennes',
            'PLATS_BIO' => 'Plats bio',
            'PRODUITS_LOCAUX' => 'Produits locaux',
        ],
        'TYPE_EVENEMENT' => [
            'PETIT_DEJEUNER' => 'Petit déjeuner',
            'BRUNCH' => 'Brunch',
            'DEJEUNER_ASSIS' => 'Déjeuner assis',
            'DINER_ASSIS' => 'Diner assis',
            'DINER_GALA' => 'Dîner de gala',
            'BANQUET' => 'Banquet',
            'COCKTAIL_DEJEUNATOIRE' => 'Cocktail déjeunatoire',
            'COCKTAIL_DINATOIRE' => 'Cocktail dînatoire',
            'AFTERWORK' => 'Afterwork / Soirée festive',
        ],
        'DISPO_JOUR_OUVERTURE' => [
            'LUNDI' => 'Lundi',
            'MARDI' => 'Mardi',
            'MERCREDI' => 'Mercredi',
            'JEUDI' => 'Jeudi',
            'VENDREDI' => 'Vendredi',
            'SAMEDI' => 'Samedi',
            'DIMANCHE' => 'Dimanche',
        ],
        'SERVICE_RESTAURANT' => [
            'ANIMATION_MUSICALE' => 'Animation musicale / DJ',
            'ATELIERS_CULINAIRES' => 'Ateliers culinaires',
            'DECORATION' => 'Décoration personnalisable',
            'ALLEMAND' => 'Allemand parlé',
            'ANGLAIS' => 'Anglais parlé',
            'KARAOKE' => 'Karaoké',
            'DEGUSTATION_PRIVEE' => 'Dégustation privée possible',
            'MENUS_PERSONNALISABLES' => 'Menus personnalisables',
            'CAFE_ACCUEIL' => 'Café d’accueil',
            'BARBECUE' => 'Barbecue',
            'SHOW_COOKING' => 'Shows cooking',
            'ROOM_SERVICE' => 'Room service',
            'VOITURIER' => 'Service voiturier',
        ],
        'EQUIPEMENT_RESTAURANT' => [
            'VESTIAIRE' => 'Vestiaire',
            'BAR_LOUNGE' => 'Espace bar / Lounge',
            'JARDIN_PATIO' => 'Jardin / Patio privatif',
            'SCENE_PISTE_DANSE' => 'Scène / Piste de danse',
            'WIFI' => 'Wifi',
            'AUDIOVISUEL' => 'Salle privatisée avec système audiovisuel',
            'MICRO' => 'Micro',
            'PARKING_PLACE' => 'Parking sur place',
            'PARKING_PROXIMITE' => 'Parking à proximité',
            'CLIMATISATION' => 'Climatisation',
            'TERRASSE' => 'Terrasse extérieure',
            'ROOFTOP' => 'Rooftop',
        ],
        'ENGAGEMENT_RSE_RESTAURANT' => [
            'CIRCUITS_COURTS' => 'Produits locaux / Circuits courts',
            'ESAT' => 'Restaurateur ESAT',
            'BIO' => 'Produits bio',
            'FRAIS' => 'Produits frais non congelés',
            'SAISON' => 'Produits de saison',
            'ANTI_GASPILLAGE' => 'Réduction du gaspillage alimentaire',
            'TRI' => 'Tri sélectif / recyclage',
            'COMPOSTAGE' => 'Compostage des biodéchets',
            'CONTENANTS' => 'Vaisselle ou contenants éco-responsables',
            'DONS' => 'Partenariats solidaires / Dons des invendus',
            'LABEL' => 'Label éco-responsable',
            'GREEN_FOOD' => 'Green Food',
            'FOURNISSEURS' => 'Fournisseurs engagés et de proximité',
            'EAU_ENERGIE' => 'Gestion raisonnée de l’eau et de l’énergie',
            'OFFRE_DURABLE' => 'Offre végétarienne / Durable',
        ],
    ];

    /** @return array<string, string> */
    public static function values(string $attribute): array
    {
        return LovRuntimeCatalog::choices($attribute) ?? self::VALUES[$attribute]
            ?? throw new \InvalidArgumentException('Attribut Restaurant inconnu.');
    }

    /** @return list<string> */
    public static function attributes(): array
    {
        return array_keys(self::VALUES);
    }

    public static function attributeId(string $attribute): int
    {
        self::values($attribute);

        return self::stableId('attribute:'.$attribute);
    }

    public static function valueId(string $attribute, string $code): int
    {
        $runtimeId = LovRuntimeCatalog::valueId($attribute, $code);
        if (null !== $runtimeId) {
            return $runtimeId;
        }
        if (!isset(self::values($attribute)[$code])) {
            throw new \InvalidArgumentException('Valeur Restaurant inconnue.');
        }

        return self::stableId('value:'.$attribute.':'.$code);
    }

    public static function valueCode(string $attribute, int $id): string
    {
        $runtimeCode = LovRuntimeCatalog::valueCode($id);
        if (null !== $runtimeCode) {
            return $runtimeCode;
        }
        $values = self::VALUES[$attribute] ?? throw new \InvalidArgumentException('Attribut Restaurant inconnu.');
        foreach (array_keys($values) as $code) {
            if (self::stableId('value:'.$attribute.':'.$code) === $id) {
                return $code;
            }
        }

        throw new \UnexpectedValueException('Identifiant Restaurant inconnu.');
    }

    /** @param list<string> $codes
     * @return list<int>
     */
    public static function valueIds(string $attribute, array $codes): array
    {
        return array_map(
            static fn (string $code): int => self::valueId($attribute, $code),
            array_values(array_unique($codes)),
        );
    }

    private static function stableId(string $value): int
    {
        return (int) sprintf('%u', crc32($value));
    }
}
