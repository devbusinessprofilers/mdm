<?php

declare(strict_types=1);

namespace App\Pim\Lov;

final class ServiceLovCatalog
{
    /** @var array<string, string> */
    private const PRESTATIONS = [
        "TS_ACCUEIL_SECURITE" => "Accueil et sécurité",
        "TS_CADEAU_CLIENT_GOODIE" => "Cadeaux clients & Goodies",
        "TS_COMMUNICATION_PUBLICITÉ" => "Communication & Publicité",
        "TS_TECHNIQUE_AUDIOVISUEL" => "Technique & Audiovisuel",
        "TS_SON_VIDEO" => "Son & Vidéo",
        "TS_ANIMATION_ARTISTE" => "Animations & Artistes",
        "TS_TRADUCTION_INTERPRETARIAT" => "Traduction & Interprétariat",
        "TS_TRANSPORT_LOGISTIQUE" => "Transports & Logistique",
        "TS_TRAITEUR" => "Traiteurs",
        "TS_DIVERS_SUR_MESURE" => "Divers & Sur-mesure",
        "TS_DIGITAL_HYBRIDE" => "Digital & Hybride",
    ];

    /**
     * Sous-prestations (CDC « D - Services Evénementiels », onglet Typologie
     * de prestataire) : un attribut par famille — code `{FAMILLE}_SS` — comme
     * les champs thématique/cadre/ambiance des lieux. Les codes de valeurs
     * sont préfixés par l'attribut.
     *
     * @var array<string, array<string, string>>
     */
    private const SOUS_PRESTATIONS = [
        "TS_ACCUEIL_SECURITE_SS" => [
            "TS_ACCUEIL_SECURITE_SS_1" => "Agents d’accueil",
            "TS_ACCUEIL_SECURITE_SS_2" => "Hôtesses",
            "TS_ACCUEIL_SECURITE_SS_3" => "Agents de sécurité / Vigiles",
            "TS_ACCUEIL_SECURITE_SS_4" => "Contrôle d’accès",
        ],
        "TS_CADEAU_CLIENT_GOODIE_SS" => [
            "TS_CADEAU_CLIENT_GOODIE_SS_1" => "Objets personnalisés / Goodies",
            "TS_CADEAU_CLIENT_GOODIE_SS_2" => "Coffrets cadeaux",
            "TS_CADEAU_CLIENT_GOODIE_SS_3" => "Artisanat local",
        ],
        "TS_COMMUNICATION_PUBLICITÉ_SS" => [
            "TS_COMMUNICATION_PUBLICITÉ_SS_1" => "Agence de communication",
            "TS_COMMUNICATION_PUBLICITÉ_SS_2" => "Graphistes",
            "TS_COMMUNICATION_PUBLICITÉ_SS_3" => "PLV / Signalétique",
            "TS_COMMUNICATION_PUBLICITÉ_SS_4" => "Fournitures pour expositions",
            "TS_COMMUNICATION_PUBLICITÉ_SS_5" => "Standistes",
            "TS_COMMUNICATION_PUBLICITÉ_SS_6" => "Sites web événementiels",
            "TS_COMMUNICATION_PUBLICITÉ_SS_7" => "Imprimeur",
        ],
        "TS_TECHNIQUE_AUDIOVISUEL_SS" => [
            "TS_TECHNIQUE_AUDIOVISUEL_SS_1" => "Décoration florale",
            "TS_TECHNIQUE_AUDIOVISUEL_SS_2" => "Décorateur / Scénographe",
            "TS_TECHNIQUE_AUDIOVISUEL_SS_3" => "Mobilier événementiel",
            "TS_TECHNIQUE_AUDIOVISUEL_SS_4" => "Scénographie immersive",
            "TS_TECHNIQUE_AUDIOVISUEL_SS_5" => "Tentes & Chapiteaux",
            "TS_TECHNIQUE_AUDIOVISUEL_SS_6" => "Réalisation audiovisuelle",
        ],
        "TS_SON_VIDEO_SS" => [
            "TS_SON_VIDEO_SS_1" => "Sonorisation",
            "TS_SON_VIDEO_SS_2" => "Vidéo / Captation",
            "TS_SON_VIDEO_SS_3" => "Éclairage & Lumière",
            "TS_SON_VIDEO_SS_4" => "Réalité virtuelle / digitale",
            "TS_SON_VIDEO_SS_5" => "Vidéoconférence",
            "TS_SON_VIDEO_SS_6" => "Location de matériel technique",
            "TS_SON_VIDEO_SS_7" => "Effets spéciaux",
        ],
        "TS_ANIMATION_ARTISTE_SS" => [
            "TS_ANIMATION_ARTISTE_SS_1" => "DJ",
            "TS_ANIMATION_ARTISTE_SS_2" => "Magicien",
            "TS_ANIMATION_ARTISTE_SS_3" => "Mentaliste",
            "TS_ANIMATION_ARTISTE_SS_4" => "Hypnotiseur",
            "TS_ANIMATION_ARTISTE_SS_5" => "Animateur / Maître de cérémonie",
            "TS_ANIMATION_ARTISTE_SS_6" => "Performeurs (danse, cirque, etc.)",
            "TS_ANIMATION_ARTISTE_SS_7" => "Spectacle clé en main",
            "TS_ANIMATION_ARTISTE_SS_8" => "Photobooth",
            "TS_ANIMATION_ARTISTE_SS_9" => "Artistes",
            "TS_ANIMATION_ARTISTE_SS_10" => "Photographe",
            "TS_ANIMATION_ARTISTE_SS_11" => "Musicien",
            "TS_ANIMATION_ARTISTE_SS_12" => "Intervenant",
            "TS_ANIMATION_ARTISTE_SS_13" => "Flair",
        ],
        "TS_TRADUCTION_INTERPRETARIAT_SS" => [
            "TS_TRADUCTION_INTERPRETARIAT_SS_1" => "Interprètes de conférence",
            "TS_TRADUCTION_INTERPRETARIAT_SS_2" => "Traducteurs simultanés",
            "TS_TRADUCTION_INTERPRETARIAT_SS_3" => "Fourniture et gestion de matériel de traduction",
        ],
        "TS_TRANSPORT_LOGISTIQUE_SS" => [
            "TS_TRANSPORT_LOGISTIQUE_SS_1" => "Transferts aéroport / gare",
            "TS_TRANSPORT_LOGISTIQUE_SS_2" => "Navettes",
            "TS_TRANSPORT_LOGISTIQUE_SS_3" => "Bus & minibus",
            "TS_TRANSPORT_LOGISTIQUE_SS_4" => "Transport VIP",
            "TS_TRANSPORT_LOGISTIQUE_SS_5" => "Logistique technique",
            "TS_TRANSPORT_LOGISTIQUE_SS_6" => "Taxis / VTC",
        ],
        "TS_TRAITEUR_SS" => [
            "TS_TRAITEUR_SS_1" => "Traiteurs",
            "TS_TRAITEUR_SS_2" => "Foodtrucks",
        ],
        "TS_DIVERS_SUR_MESURE_SS" => [
            "TS_DIVERS_SUR_MESURE_SS_1" => "Expériences exclusives personnalisées",
            "TS_DIVERS_SUR_MESURE_SS_2" => "Services atypiques ou innovants",
        ],
        "TS_DIGITAL_HYBRIDE_SS" => [
            "TS_DIGITAL_HYBRIDE_SS_1" => "Application événementielle",
            "TS_DIGITAL_HYBRIDE_SS_2" => "Site web événementiel",
            "TS_DIGITAL_HYBRIDE_SS_3" => "Inscription et billetterie",
            "TS_DIGITAL_HYBRIDE_SS_4" => "Checking & badges",
            "TS_DIGITAL_HYBRIDE_SS_5" => "Programmes en ligne",
            "TS_DIGITAL_HYBRIDE_SS_6" => "Matchmaking",
            "TS_DIGITAL_HYBRIDE_SS_7" => "Rendez-vous",
            "TS_DIGITAL_HYBRIDE_SS_8" => "Streaming événementiel",
            "TS_DIGITAL_HYBRIDE_SS_9" => "Webinar",
            "TS_DIGITAL_HYBRIDE_SS_10" => "Création de contenus visuels instantanés",
            "TS_DIGITAL_HYBRIDE_SS_11" => "Création de visuels et supports événementiels",
            "TS_DIGITAL_HYBRIDE_SS_12" => "Animation interactive",
        ],
    ];

    /**
     * Nom de champ (formulaire, payload marketplace) de chaque attribut de
     * sous-prestations.
     *
     * @var array<string, string>
     */
    public const SOUS_PRESTATION_FIELDS = [
        "TS_ACCUEIL_SECURITE_SS" => "sousPrestationsAccueilSecurite",
        "TS_CADEAU_CLIENT_GOODIE_SS" => "sousPrestationsCadeauxGoodies",
        "TS_COMMUNICATION_PUBLICITÉ_SS" => "sousPrestationsCommunicationPublicite",
        "TS_TECHNIQUE_AUDIOVISUEL_SS" => "sousPrestationsTechniqueAudiovisuel",
        "TS_SON_VIDEO_SS" => "sousPrestationsSonVideo",
        "TS_ANIMATION_ARTISTE_SS" => "sousPrestationsAnimationsArtistes",
        "TS_TRADUCTION_INTERPRETARIAT_SS" => "sousPrestationsTraductionInterpretariat",
        "TS_TRANSPORT_LOGISTIQUE_SS" => "sousPrestationsTransportsLogistique",
        "TS_TRAITEUR_SS" => "sousPrestationsTraiteurs",
        "TS_DIVERS_SUR_MESURE_SS" => "sousPrestationsDiversSurMesure",
        "TS_DIGITAL_HYBRIDE_SS" => "sousPrestationsDigitalHybride",
    ];

    /** @return array<string, string> */
    public static function prestations(): array
    {
        return LovRuntimeCatalog::choices('TYPE_PRESTATAIRE') ?? self::PRESTATIONS;
    }

    /** @return array<string, string> attribut de sous-prestations => libellé de la famille */
    public static function sousPrestationAttributes(): array
    {
        $attributes = [];
        foreach (array_keys(self::SOUS_PRESTATIONS) as $attribute) {
            $famille = self::familleOf($attribute);
            $attributes[$attribute] = self::prestations()[$famille] ?? self::PRESTATIONS[$famille] ?? $famille;
        }

        return $attributes;
    }

    /** @return array<string, string> */
    public static function sousPrestationsFor(string $attributeCode): array
    {
        return LovRuntimeCatalog::choices($attributeCode) ?? self::SOUS_PRESTATIONS[$attributeCode] ?? [];
    }

    /**
     * Dictionnaire à plat, toutes familles (complétude, import, API).
     *
     * @return array<string, string>
     */
    public static function sousPrestations(): array
    {
        $all = [];
        foreach (array_keys(self::SOUS_PRESTATIONS) as $attribute) {
            $all += self::sousPrestationsFor($attribute);
        }

        return $all;
    }

    /** Code de la famille TYPE_PRESTATAIRE d'un attribut de sous-prestations. */
    public static function familleOf(string $sousPrestationAttribute): string
    {
        return (string) preg_replace('/_SS$/', '', $sousPrestationAttribute);
    }

    /** Attribut de rattachement d'un code de sous-prestation (préfixe avant « _SS_ »). */
    public static function sousPrestationAttributeOf(string $code): string
    {
        $position = strrpos($code, '_SS_');
        if (false === $position) {
            throw new \InvalidArgumentException("Sous-prestation Service inconnue.");
        }

        return substr($code, 0, $position).'_SS';
    }

    /** Code de la famille TYPE_PRESTATAIRE parente d'un code de sous-prestation. */
    public static function parentOf(string $sousPrestationCode): string
    {
        return self::familleOf(self::sousPrestationAttributeOf($sousPrestationCode));
    }

    public static function attributeId(): int
    {
        return self::stableId("attribute:TYPE_PRESTATAIRE");
    }

    public static function sousPrestationAttributeId(string $attributeCode): int
    {
        return self::stableId("attribute:".$attributeCode);
    }

    public static function sousPrestationValueId(string $attributeCode, string $code): int
    {
        $runtimeId = LovRuntimeCatalog::valueId($attributeCode, $code);
        if (null !== $runtimeId) { return $runtimeId; }
        if (!isset(self::SOUS_PRESTATIONS[$attributeCode][$code])) {
            throw new \InvalidArgumentException("Sous-prestation Service inconnue.");
        }

        return self::stableId("value:".$attributeCode.":".$code);
    }

    public static function sousPrestationValueCode(string $attributeCode, int $id): string
    {
        $runtimeCode = LovRuntimeCatalog::valueCode($id);
        if (null !== $runtimeCode) { return $runtimeCode; }
        foreach (array_keys(self::SOUS_PRESTATIONS[$attributeCode] ?? []) as $code) {
            if (self::stableId('value:'.$attributeCode.':'.$code) === $id) {
                return $code;
            }
        }

        throw new \UnexpectedValueException(
            "Identifiant de sous-prestation Service inconnu.",
        );
    }

    /** @param list<string> $codes
     *  @return list<int>
     */
    public static function sousPrestationValueIds(string $attributeCode, array $codes): array
    {
        return array_map(
            static fn (string $code): int => self::sousPrestationValueId($attributeCode, $code),
            array_values(array_unique($codes)),
        );
    }

    public static function valueId(string $code): int
    {
        $runtimeId = LovRuntimeCatalog::valueId('TYPE_PRESTATAIRE', $code);
        if (null !== $runtimeId) { return $runtimeId; }
        if (!isset(self::PRESTATIONS[$code])) {
            throw new \InvalidArgumentException("Prestation Service inconnue.");
        }

        return self::stableId("value:TYPE_PRESTATAIRE:" . $code);
    }

    public static function valueCode(int $id): string
    {
        $runtimeCode = LovRuntimeCatalog::valueCode($id);
        if (null !== $runtimeCode) { return $runtimeCode; }
        foreach (array_keys(self::PRESTATIONS) as $code) {
            if (self::stableId('value:TYPE_PRESTATAIRE:'.$code) === $id) {
                return $code;
            }
        }

        throw new \UnexpectedValueException(
            "Identifiant de prestation Service inconnu.",
        );
    }

    /** @param list<string> $codes
     *  @return list<int>
     */
    public static function valueIds(array $codes): array
    {
        return array_map(
            self::valueId(...),
            array_values(array_unique($codes)),
        );
    }

    private static function stableId(string $value): int
    {
        return (int) sprintf("%u", crc32($value));
    }
}
