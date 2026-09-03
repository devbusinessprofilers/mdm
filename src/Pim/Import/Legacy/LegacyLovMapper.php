<?php

declare(strict_types=1);

namespace App\Pim\Import\Legacy;

use App\Pim\Lov\LieuLovCatalog;

/**
 * Traduit les libellés du CSV production vers les codes LOV du PIM.
 * Toute valeur non reconnue produit un warning plutôt qu'un échec de ligne.
 */
final class LegacyLovMapper
{
    /** Alias explicites : libellé legacy normalisé → code typologie. */
    private const TYPOLOGIE_ALIASES = [
        'circuits automobiles / kartings' => 'GENERALE_TYPOLOGIE_21',
    ];

    private const CADRE_ENV = [
        'mer' => 'TA_CADRE_ENV_1',
        'au vert' => 'TA_CADRE_ENV_2',
        'campagne' => 'TA_CADRE_ENV_3',
        'montagne' => 'TA_CADRE_ENV_4',
        'lac' => 'TA_CADRE_ENV_5',
        'centre ville' => 'TA_CADRE_ENV_6',
    ];

    private const THEMATIQUES = [
        'bien-etre' => 'TA_THEMATIQUE_1',
        'golf' => 'TA_THEMATIQUE_2',
        'eco-responsable' => 'TA_THEMATIQUE_3',
        'eco responsable' => 'TA_THEMATIQUE_3',
        'gastronomique' => 'TA_THEMATIQUE_4',
        'oenotourisme' => 'TA_THEMATIQUE_5',
        'ski' => 'TA_THEMATIQUE_6',
        'chateaux' => 'TA_THEMATIQUE_7',
        'chateau' => 'TA_THEMATIQUE_7',
        'comme a la maison' => 'TA_THEMATIQUE_8',
    ];

    private const IGNORED_THEMES = ['pas de theme', 'ile'];

    private const CLASSIFICATION = [
        '★★' => 'GENERALE_TYPOLOGIE_1',
        '★★★' => 'GENERALE_TYPOLOGIE_2',
        '★★★★' => 'GENERALE_TYPOLOGIE_3',
        '★★★★★' => 'GENERALE_TYPOLOGIE_4',
        'palace' => 'GENERALE_TYPOLOGIE_5',
    ];

    private const COUNTRY_CODES = [
        'france' => 'FR', 'dom tom' => 'FR', 'monaco' => 'MC', 'allemagne' => 'DE',
        'italie' => 'IT', 'espagne' => 'ES', 'belgique' => 'BE', 'irlande' => 'IE',
        'royaume-uni' => 'GB', 'angleterre' => 'GB', 'ecosse' => 'GB', 'suisse' => 'CH',
        'portugal' => 'PT', 'autriche' => 'AT', 'lituanie' => 'LT', 'pays-bas' => 'NL',
        'luxembourg' => 'LU', 'grece' => 'GR', 'croatie' => 'HR', 'malte' => 'MT',
        'pologne' => 'PL', 'republique tcheque' => 'CZ', 'hongrie' => 'HU',
        'danemark' => 'DK', 'suede' => 'SE', 'norvege' => 'NO', 'finlande' => 'FI',
        'maroc' => 'MA', 'tunisie' => 'TN', 'etats-unis' => 'US',
    ];

    /** @var array<string, string> libellé typologie normalisé → code */
    private array $typologieLabels;

    public function __construct()
    {
        $this->typologieLabels = [];
        foreach (LieuLovCatalog::choicesFor('GENERALE_TYPOLOGIE') as $code => $label) {
            $this->typologieLabels[self::normalize($label)] = $code;
        }
    }

    /**
     * @return array{codes: list<string>, hebergement: bool, warnings: list<string>}
     */
    public function typologies(string $classification, string $gamme, string $typeLieuxJson): array
    {
        $codes = [];
        $hebergement = false;
        $warnings = [];

        $classification = trim($classification);
        if ('' !== $classification) {
            $code = self::CLASSIFICATION[$classification] ?? self::CLASSIFICATION[self::normalize($classification)] ?? null;
            if (null === $code) {
                $warnings[] = 'classification_inconnue';
            } else {
                $codes[] = $code;
            }
        }
        if ('Centre de congrès' === $gamme) {
            $codes[] = 'GENERALE_TYPOLOGIE_20';
        }
        foreach ($this->decodeJsonList($typeLieuxJson, $warnings, 'type_lieux_json_invalide') as $label) {
            $normalized = self::normalize($label);
            if ('avec hebergements' === $normalized) {
                $hebergement = true;
                continue;
            }
            $code = $this->typologieLabels[$normalized] ?? self::TYPOLOGIE_ALIASES[$normalized] ?? null;
            if (null === $code) {
                $warnings[] = 'type_lieu_non_mappe';
                $code = 'GENERALE_TYPOLOGIE_40';
            }
            $codes[] = $code;
        }
        $codes = array_values(array_unique($codes));
        LieuLovCatalog::assertValidMany('GENERALE_TYPOLOGIE', $codes);

        return ['codes' => $codes, 'hebergement' => $hebergement, 'warnings' => $warnings];
    }

    /**
     * @return array{cadreEnv: list<string>, thematiques: list<string>, esat: bool, rse: bool, warnings: list<string>}
     */
    public function themes(string $thematiqueJson): array
    {
        $cadreEnv = [];
        $thematiques = [];
        $warnings = [];
        $esat = false;
        $rse = false;
        foreach ($this->decodeJsonList($thematiqueJson, $warnings, 'thematique_json_invalide') as $label) {
            $normalized = self::normalize($label);
            // « Esat » → typologie « Lieu ESAT » ; « RSE » → case à cocher dédiée. Pas des thèmes.
            if ('esat' === $normalized) {
                $esat = true;
                continue;
            }
            if ('rse' === $normalized) {
                $rse = true;
                continue;
            }
            if (in_array($normalized, self::IGNORED_THEMES, true)) {
                continue;
            }
            if (isset(self::CADRE_ENV[$normalized])) {
                $cadreEnv[] = self::CADRE_ENV[$normalized];
            } elseif (isset(self::THEMATIQUES[$normalized])) {
                $thematiques[] = self::THEMATIQUES[$normalized];
            } else {
                $warnings[] = 'thematique_non_mappee';
            }
        }
        $cadreEnv = array_values(array_unique($cadreEnv));
        $thematiques = array_values(array_unique($thematiques));
        LieuLovCatalog::assertValidMany('TA_CADRE_ENV', $cadreEnv);
        LieuLovCatalog::assertValidMany('TA_THEMATIQUE', $thematiques);

        return ['cadreEnv' => $cadreEnv, 'thematiques' => $thematiques, 'esat' => $esat, 'rse' => $rse, 'warnings' => $warnings];
    }

    public function countryCode(string $pays): ?string
    {
        return self::COUNTRY_CODES[self::normalize($pays)] ?? null;
    }

    /**
     * @param list<string> $warnings
     *
     * @return list<string>
     */
    private function decodeJsonList(string $json, array &$warnings, string $warningCode): array
    {
        $json = trim($json);
        if ('' === $json) {
            return [];
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            $warnings[] = $warningCode;

            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($value): string => is_string($value) ? trim($value) : '',
            $decoded,
        ), static fn (string $value): bool => '' !== $value));
    }

    private static function normalize(string $value): string
    {
        $value = str_replace(['’', '‘'], "'", mb_strtolower(trim($value)));
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT', $value);

        return preg_replace('/\s+/', ' ', false === $ascii ? $value : $ascii) ?? $value;
    }
}
