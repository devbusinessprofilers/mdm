<?php

declare(strict_types=1);

namespace App\Pim\Import\Legacy;

/**
 * Traduit les libellés restaurant du CSV production vers les codes LOV du PIM
 * (RestaurantLovCatalog). Les thématiques legacy se répartissent entre types
 * de restaurant et engagements RSE.
 */
final class LegacyRestaurantLovMapper
{
    /** Thématique legacy normalisée → code TYPE_RESTAURANT. */
    private const TYPES_RESTAURANT = [
        'gastronomique' => 'GASTRONOMIQUE',
        'mer' => 'BORD_DE_MER',
        'au vert' => 'AU_VERT',
        'lac' => 'BORD_EAU',
        'montagne' => 'RESTAURANT_ALTITUDE',
    ];

    /** Thématique legacy normalisée → code ENGAGEMENT_RSE_RESTAURANT. */
    private const ENGAGEMENTS_RSE = [
        'esat' => 'ESAT',
    ];

    private const IGNORED_THEMES = ['pas de theme'];

    /**
     * @return array{typesRestaurant: list<string>, engagementsRse: list<string>, warnings: list<string>}
     */
    public function themes(string $thematiqueJson): array
    {
        $typesRestaurant = [];
        $engagementsRse = [];
        $warnings = [];
        $thematiqueJson = trim($thematiqueJson);
        if ('' === $thematiqueJson) {
            return ['typesRestaurant' => [], 'engagementsRse' => [], 'warnings' => []];
        }
        $decoded = json_decode($thematiqueJson, true);
        if (!is_array($decoded)) {
            return ['typesRestaurant' => [], 'engagementsRse' => [], 'warnings' => ['thematique_json_invalide']];
        }
        foreach (array_filter($decoded, is_string(...)) as $label) {
            $normalized = self::normalize($label);
            if (in_array($normalized, self::IGNORED_THEMES, true)) {
                continue;
            }
            if (isset(self::TYPES_RESTAURANT[$normalized])) {
                $typesRestaurant[] = self::TYPES_RESTAURANT[$normalized];
            } elseif (isset(self::ENGAGEMENTS_RSE[$normalized])) {
                $engagementsRse[] = self::ENGAGEMENTS_RSE[$normalized];
            } else {
                $warnings[] = 'thematique_non_mappee';
            }
        }

        return [
            'typesRestaurant' => array_values(array_unique($typesRestaurant)),
            'engagementsRse' => array_values(array_unique($engagementsRse)),
            'warnings' => $warnings,
        ];
    }

    private static function normalize(string $value): string
    {
        $value = str_replace(['’', '‘'], "'", mb_strtolower(trim($value)));
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT', $value);

        return preg_replace('/\s+/', ' ', false === $ascii ? $value : $ascii) ?? $value;
    }
}
