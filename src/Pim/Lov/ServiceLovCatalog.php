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

    /** @return array<string, string> */
    public static function prestations(): array
    {
        return LovRuntimeCatalog::choices('TYPE_PRESTATAIRE') ?? self::PRESTATIONS;
    }

    public static function attributeId(): int
    {
        return self::stableId("attribute:TYPE_PRESTATAIRE");
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
            if (self::valueId($code) === $id) {
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
