<?php

declare(strict_types=1);

namespace App\Pim\Lov;

final class ActiviteLovCatalog
{
    /** @var array<string, array<string, string>> */
    private const CHOICES = [
        'TYPE_EXT_INT' => [
            'TYPE_EXT_INT_1' => 'Intérieur',
            'TYPE_EXT_INT_2' => 'Extérieur',
        ],
        'LANGUE_PARLEE' => [
            'LANGUE_PARLEE_1' => 'Français',
            'LANGUE_PARLEE_2' => 'Anglais',
            'LANGUE_PARLEE_3' => 'Espagnol',
            'LANGUE_PARLEE_4' => 'Allemand',
            'LANGUE_PARLEE_5' => 'Portugais',
            'LANGUE_PARLEE_6' => 'Néerlandais',
        ],
        'ENGAGEMENT_RSE' => ['ENGAGEMENT_RSE_1' => 'Prestataire ESAT'],
        'OBJECTIF_SEMINAIRE' => [
            'OBJECTIF_SEMINAIRE_1' => 'Fédérer la cohésion et la dynamique d’équipe',
            'OBJECTIF_SEMINAIRE_2' => 'Communiquer & Collaborer',
            'OBJECTIF_SEMINAIRE_3' => 'Motiver & Engager',
            'OBJECTIF_SEMINAIRE_4' => 'Développer et encourager la culture d’entreprise',
            'OBJECTIF_SEMINAIRE_5' => 'Sensibiliser et RSE',
            'OBJECTIF_SEMINAIRE_6' => 'Fidéliser & Récompenser',
            'OBJECTIF_SEMINAIRE_7' => 'Gérer les relations & les tensions',
            'OBJECTIF_SEMINAIRE_8' => 'Stimuler & Challenger',
        ],
        'THEMATIQUE_ACTIVITE' => [
            'TA_SPORTIVE_LUDIQUE' => 'Sportives & Ludiques',
            'TA_SENSATION_SPORT_MECA' => 'Sensations fortes & Sports mécaniques',
            'TA_NAUTIQUE_AQUATIQUE' => 'Nautiques & Aquatiques',
            'TA_CULINAIRE_OENOLOGIQUE' => 'Culinaires & Œnologiques',
            'TA_CREATIVE_ARTISTIQUE_MUSICALE' => 'Créatives, Artistiques & Musicales',
            'TA_CULTURELLE_REFLEXION_DECOUVERTE' => 'Culturelles, Réflexions & Découvertes',
            'TA_NATURE_RSE' => 'Nature & RSE',
            'TA_BIEN_ETRE_DETENTE' => 'Bien-être & Détente',
            'TA_DIGITAL_HIGH_TECH' => 'Digital & High-Tech',
        ],
        // Sous-thématiques (Bible, onglet LOV) : le code de la thématique
        // parente est le préfixe du code de chaque sous-thématique.
        'SOUS_THEMATIQUE_ACTIVITE' => [
            'TA_SPORTIVE_LUDIQUE_SS_1' => 'Olympiades',
            'TA_SPORTIVE_LUDIQUE_SS_2' => 'Parc aventure & Accrobranche',
            'TA_SPORTIVE_LUDIQUE_SS_3' => 'Escalade',
            'TA_SPORTIVE_LUDIQUE_SS_4' => 'Laser Game & Paintball',
            'TA_SPORTIVE_LUDIQUE_SS_5' => 'Sports collectifs',
            'TA_SPORTIVE_LUDIQUE_SS_6' => 'Équitation',
            'TA_SPORTIVE_LUDIQUE_SS_7' => 'Survie & Bootcamp',
            'TA_SPORTIVE_LUDIQUE_SS_8' => 'Défis sportifs & Koh-Lanta',
            'TA_SPORTIVE_LUDIQUE_SS_9' => 'Montagne & Neige',
            'TA_SPORTIVE_LUDIQUE_SS_10' => 'Chasse au trésor & Rallyes',
            'TA_SPORTIVE_LUDIQUE_SS_11' => 'Randonnée, Course d’orientation',
            'TA_SPORTIVE_LUDIQUE_SS_12' => 'Tir à l’arc & Jeux d’adresse',
            'TA_SENSATION_SPORT_MECA_SS_1' => 'Karting',
            'TA_SENSATION_SPORT_MECA_SS_2' => 'Rallye automobile',
            'TA_SENSATION_SPORT_MECA_SS_3' => 'Quad, Buggy & SSV',
            'TA_SENSATION_SPORT_MECA_SS_4' => 'Activités aériennes (parapente, montgolfière, hélico…)',
            'TA_SENSATION_SPORT_MECA_SS_5' => 'Sensations fortes (saut à l’élastique, chute libre, simulateur...)',
            'TA_SENSATION_SPORT_MECA_SS_6' => 'Motoneige',
            'TA_NAUTIQUE_AQUATIQUE_SS_1' => 'Canoë, Kayak & Paddle',
            'TA_NAUTIQUE_AQUATIQUE_SS_2' => 'Voile & Catamaran',
            'TA_NAUTIQUE_AQUATIQUE_SS_3' => 'Sports d’eaux vives',
            'TA_NAUTIQUE_AQUATIQUE_SS_4' => 'Plongée & Snorkeling',
            'TA_NAUTIQUE_AQUATIQUE_SS_5' => 'Croisières & Balades en bateau',
            'TA_CULINAIRE_OENOLOGIQUE_SS_1' => 'Œnologie & Dégustation de vins',
            'TA_CULINAIRE_OENOLOGIQUE_SS_2' => 'Mixologie & Cocktails',
            'TA_CULINAIRE_OENOLOGIQUE_SS_3' => 'Ateliers de cuisine & Gastronomie',
            'TA_CULINAIRE_OENOLOGIQUE_SS_4' => 'Ateliers sucrés',
            'TA_CULINAIRE_OENOLOGIQUE_SS_5' => 'Atelier de chocolat',
            'TA_CULINAIRE_OENOLOGIQUE_SS_6' => 'Food Tours & Découvertes culinaires',
            'TA_CREATIVE_ARTISTIQUE_MUSICALE_SS_1' => 'Atelier de peinture & Sculpture',
            'TA_CREATIVE_ARTISTIQUE_MUSICALE_SS_2' => 'Atelier de mosaïque & Céramique',
            'TA_CREATIVE_ARTISTIQUE_MUSICALE_SS_3' => 'Parfum & Cosmétique',
            'TA_CREATIVE_ARTISTIQUE_MUSICALE_SS_4' => 'Théâtre, Improvisation & Prise de parole',
            'TA_CREATIVE_ARTISTIQUE_MUSICALE_SS_5' => 'Musique, Chant & Danse',
            'TA_CREATIVE_ARTISTIQUE_MUSICALE_SS_6' => 'Écriture & Storytelling',
            'TA_CREATIVE_ARTISTIQUE_MUSICALE_SS_7' => 'Cinéma & Réalisation',
            'TA_CREATIVE_ARTISTIQUE_MUSICALE_SS_8' => 'Atelier artisanal',
            'TA_CREATIVE_ARTISTIQUE_MUSICALE_SS_9' => 'Atelier bijoux',
            'TA_CREATIVE_ARTISTIQUE_MUSICALE_SS_10' => 'Doublage de films & Clip Vidéo',
            'TA_CREATIVE_ARTISTIQUE_MUSICALE_SS_11' => 'Atelier floral (couronnes, terrariums, kokedama)',
            'TA_CULTURELLE_REFLEXION_DECOUVERTE_SS_1' => 'Visites culturelles & Patrimoine',
            'TA_CULTURELLE_REFLEXION_DECOUVERTE_SS_2' => 'Jeux de piste & Rallyes urbains',
            'TA_CULTURELLE_REFLEXION_DECOUVERTE_SS_3' => 'Escape Games',
            'TA_CULTURELLE_REFLEXION_DECOUVERTE_SS_4' => 'Murder Party & Enquêtes',
            'TA_CULTURELLE_REFLEXION_DECOUVERTE_SS_5' => 'Quiz, Blind Tests & Jeux de réflexion',
            'TA_CULTURELLE_REFLEXION_DECOUVERTE_SS_6' => 'Jeux de rôle & Expériences immersives',
            'TA_CULTURELLE_REFLEXION_DECOUVERTE_SS_7' => 'Simulateurs & Réalité immersive',
            'TA_CULTURELLE_REFLEXION_DECOUVERTE_SS_8' => 'Casino & Jeux de stratégie',
            'TA_CULTURELLE_REFLEXION_DECOUVERTE_SS_9' => 'Spectacles (magie, mentalisme, hypnose…)',
            'TA_CULTURELLE_REFLEXION_DECOUVERTE_SS_10' => 'Action Games',
            'TA_NATURE_RSE_SS_1' => 'Fresque du Climat, Fresque de la Biodiversité…',
            'TA_NATURE_RSE_SS_2' => 'Randonnée, marche en forêt, orientation nature…',
            'TA_NATURE_RSE_SS_3' => 'Atelier zéro déchet, cosmétiques naturels, recyclage…',
            'TA_NATURE_RSE_SS_4' => 'Construction de nichoirs, hôtels à insectes, mobilier en palettes…',
            'TA_NATURE_RSE_SS_5' => 'Plantation d’arbres, nettoyage de plages ou de forêts, collecte de déchets…',
            'TA_BIEN_ETRE_DETENTE_SS_1' => 'Yoga & Méditation',
            'TA_BIEN_ETRE_DETENTE_SS_2' => 'Sophrologie & Relaxation',
            'TA_BIEN_ETRE_DETENTE_SS_3' => 'Massages & Soins',
            'TA_BIEN_ETRE_DETENTE_SS_4' => 'Coaching & Développement personnel',
            'TA_BIEN_ETRE_DETENTE_SS_5' => 'Bien-être au travail (gestion du stress, respiration, pleine conscience…)',
            'TA_DIGITAL_HIGH_TECH_SS_1' => 'Réalité virtuelle & Réalité augmentée',
            'TA_DIGITAL_HIGH_TECH_SS_2' => 'Expériences digitales interactives',
            'TA_DIGITAL_HIGH_TECH_SS_3' => 'Serious Games & Escape Games digitaux',
            'TA_DIGITAL_HIGH_TECH_SS_4' => 'Intelligence artificielle & Innovation',
        ],
    ];

    /** @return array<string, string> */
    public static function choicesFor(string $attributeCode): array
    {
        return LovRuntimeCatalog::choices($attributeCode) ?? self::CHOICES[$attributeCode] ?? [];
    }

    /** @return array<string, array<string, string>> */
    public static function allChoices(): array
    {
        return self::CHOICES;
    }

    public static function attributeId(string $attributeCode): int
    {
        return self::stableId('attribute:'.$attributeCode);
    }

    public static function valueId(
        string $attributeCode,
        string $valueCode,
    ): int {
        $runtimeId = LovRuntimeCatalog::valueId($attributeCode, $valueCode);
        if (null !== $runtimeId) { return $runtimeId; }
        if (!isset(self::CHOICES[$attributeCode][$valueCode])) {
            throw new \InvalidArgumentException('Valeur de LOV Activité inconnue.');
        }

        return self::stableId('value:'.$attributeCode.':'.$valueCode);
    }

    public static function valueCode(
        string $attributeCode,
        int $valueId,
    ): string {
        $runtimeCode = LovRuntimeCatalog::valueCode($valueId);
        if (null !== $runtimeCode) { return $runtimeCode; }
        $choices = self::CHOICES[$attributeCode] ?? throw new \InvalidArgumentException('Attribut de LOV Activité inconnu.');
        foreach ($choices as $code => $_label) {
            if (self::stableId('value:'.$attributeCode.':'.$code) === $valueId) {
                return $code;
            }
        }
        throw new \UnexpectedValueException('Identifiant de LOV Activité inconnu.');
    }

    /**
     * @param list<string> $codes
     *
     * @return list<int>
     */
    public static function valueIds(string $attributeCode, array $codes): array
    {
        return array_map(
            static fn (string $code): int => self::valueId(
                $attributeCode,
                $code,
            ),
            array_values(array_unique($codes)),
        );
    }

    /** Code de la thématique parente d'une sous-thématique (préfixe avant « _SS_ »). */
    public static function parentOf(string $sousThematiqueCode): string
    {
        $position = strrpos($sousThematiqueCode, '_SS_');
        if (false === $position || !isset(self::CHOICES['SOUS_THEMATIQUE_ACTIVITE'][$sousThematiqueCode])) {
            throw new \InvalidArgumentException('Sous-thématique d\'activité inconnue.');
        }

        return substr($sousThematiqueCode, 0, $position);
    }

    /** @return array<string, string> sous-thématiques (code => libellé) d'une thématique */
    public static function sousThematiquesFor(string $thematiqueCode): array
    {
        return array_filter(
            self::choicesFor('SOUS_THEMATIQUE_ACTIVITE'),
            static fn (string $code): bool => str_starts_with($code, $thematiqueCode.'_SS_'),
            ARRAY_FILTER_USE_KEY,
        );
    }

    private static function stableId(string $value): int
    {
        return (int) sprintf('%u', crc32($value));
    }
}
