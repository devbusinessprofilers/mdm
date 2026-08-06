<?php

declare(strict_types=1);

namespace App\Pim\Import\Legacy;

/**
 * Traduit les libellés activité du CSV production vers les codes LOV du PIM.
 * La thématique PIM est mono-valuée : le premier type mappable est retenu.
 */
final class LegacyActiviteLovMapper
{
    /** Libellé « Type d'activités » normalisé → code THEMATIQUE_ACTIVITE. */
    private const THEMATIQUES = [
        'sportives' => 'TA_SPORTIVE_LUDIQUE',
        'sensations fortes & sports mecaniques' => 'TA_SENSATION_SPORT_MECA',
        'aeriennes' => 'TA_SENSATION_SPORT_MECA',
        'nautiques' => 'TA_NAUTIQUE_AQUATIQUE',
        'culinaires & oenologiques' => 'TA_CULINAIRE_OENOLOGIQUE',
        'creatives, artistiques & musicales' => 'TA_CREATIVE_ARTISTIQUE_MUSICALE',
        'culturelles & decouvertes' => 'TA_CULTURELLE_REFLEXION_DECOUVERTE',
        'nature' => 'TA_NATURE_RSE',
        'rse' => 'TA_NATURE_RSE',
        'detentes' => 'TA_BIEN_ETRE_DETENTE',
        'digitales, high tech' => 'TA_DIGITAL_HIGH_TECH',
        // « Insolites » n'a pas d'équivalent dans le catalogue.
    ];

    /** Libellé objectif normalisé → code OBJECTIF_SEMINAIRE. */
    private const OBJECTIFS = [
        "cohesion d'equipe" => 'OBJECTIF_SEMINAIRE_1',
        'federer' => 'OBJECTIF_SEMINAIRE_1',
        'integration' => 'OBJECTIF_SEMINAIRE_1',
        'communiquer' => 'OBJECTIF_SEMINAIRE_2',
        'motiver' => 'OBJECTIF_SEMINAIRE_3',
        'sensibiliser' => 'OBJECTIF_SEMINAIRE_5',
        'recompenser' => 'OBJECTIF_SEMINAIRE_6',
        'fideliser' => 'OBJECTIF_SEMINAIRE_6',
        'gerer les tensions' => 'OBJECTIF_SEMINAIRE_7',
        'animer' => 'OBJECTIF_SEMINAIRE_8',
        'challenger' => 'OBJECTIF_SEMINAIRE_8',
        'stimuler' => 'OBJECTIF_SEMINAIRE_8',
    ];

    /**
     * @return array{thematiques: list<string>, warnings: list<string>}
     */
    public function thematiques(string $typesJson): array
    {
        $warnings = [];
        $typesJson = trim($typesJson);
        if ('' === $typesJson) {
            return ['thematiques' => [], 'warnings' => []];
        }
        $decoded = json_decode($typesJson, true);
        if (!is_array($decoded)) {
            return ['thematiques' => [], 'warnings' => ['type_activite_json_invalide']];
        }
        $thematiques = [];
        foreach (array_filter($decoded, is_string(...)) as $label) {
            $code = self::THEMATIQUES[self::normalize($label)] ?? null;
            if (null === $code) {
                $warnings[] = 'type_activite_non_mappe';
                continue;
            }
            $thematiques[] = $code;
        }

        return ['thematiques' => array_values(array_unique($thematiques)), 'warnings' => $warnings];
    }

    /**
     * @return array{codes: list<string>, warnings: list<string>}
     */
    public function objectifs(string $objectifsText): array
    {
        $codes = [];
        $warnings = [];
        foreach (preg_split('/\R+/', trim($objectifsText), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $label) {
            $code = self::OBJECTIFS[self::normalize($label)] ?? null;
            if (null === $code) {
                $warnings[] = 'objectif_non_mappe';
                continue;
            }
            $codes[] = $code;
        }

        return ['codes' => array_values(array_unique($codes)), 'warnings' => $warnings];
    }

    private static function normalize(string $value): string
    {
        $value = str_replace(['’', '‘'], "'", mb_strtolower(trim($value)));
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT', $value);

        return preg_replace('/\s+/', ' ', false === $ascii ? $value : $ascii) ?? $value;
    }
}
