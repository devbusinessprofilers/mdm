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
        foreach (self::choicesFor($attributeCode) as $code => $_label) {
            if (self::valueId($attributeCode, $code) === $valueId) {
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

    private static function stableId(string $value): int
    {
        return (int) sprintf('%u', crc32($value));
    }
}
