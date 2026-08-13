<?php

declare(strict_types=1);

namespace App\Pim\Import\Legacy;

/** Traduit le « Type de prestataire » du CSV production vers les codes TYPE_PRESTATAIRE. */
final class LegacyServiceLovMapper
{
    /** Libellé legacy normalisé → code TYPE_PRESTATAIRE. */
    private const PRESTATIONS = [
        'traiteurs' => 'TS_TRAITEUR',
        'transporteurs' => 'TS_TRANSPORT_LOGISTIQUE',
        'animations evenementielles' => 'TS_ANIMATION_ARTISTE',
        'photographes' => 'TS_ANIMATION_ARTISTE',
        'goodies' => 'TS_CADEAU_CLIENT_GOODIE',
        'realisations audiovisuelles - videos - visio' => 'TS_SON_VIDEO',
        'techniques - sonorisations' => 'TS_SON_VIDEO',
        'location de mobiliers / materiels' => 'TS_TECHNIQUE_AUDIOVISUEL',
        'fleuristes / decorations evenementielles' => 'TS_TECHNIQUE_AUDIOVISUEL',
        'constructions ephemeres (chapiteau, stand...)' => 'TS_TECHNIQUE_AUDIOVISUEL',
        'traductions - interpretes de conferences' => 'TS_TRADUCTION_INTERPRETARIAT',
        'accueil et securite' => 'TS_ACCUEIL_SECURITE',
        'imprimeurs' => 'TS_COMMUNICATION_PUBLICITÉ',
        'communications - pub' => 'TS_COMMUNICATION_PUBLICITÉ',
        'signaletiques evenementielles' => 'TS_COMMUNICATION_PUBLICITÉ',
        'apps et sites web evenementiels' => 'TS_DIGITAL_HYBRIDE',
    ];

    /**
     * Sous-prestation équivalente quand le type legacy est plus fin que la
     * famille TYPE_PRESTATAIRE (granularité conservée, aucune donnée perdue).
     *
     * @var array<string, list<string>>
     */
    private const SOUS_PRESTATIONS = [
        'traiteurs' => ['TS_TRAITEUR_SS_1'],
        'techniques - sonorisations' => ['TS_SON_VIDEO_SS_1'],
        'realisations audiovisuelles - videos - visio' => ['TS_SON_VIDEO_SS_2', 'TS_SON_VIDEO_SS_5'],
        'photographes' => ['TS_ANIMATION_ARTISTE_SS_10'],
        'imprimeurs' => ['TS_COMMUNICATION_PUBLICITÉ_SS_7'],
        'signaletiques evenementielles' => ['TS_COMMUNICATION_PUBLICITÉ_SS_3'],
        'fleuristes / decorations evenementielles' => ['TS_TECHNIQUE_AUDIOVISUEL_SS_1'],
        'constructions ephemeres (chapiteau, stand...)' => ['TS_TECHNIQUE_AUDIOVISUEL_SS_5'],
        'location de mobiliers / materiels' => ['TS_TECHNIQUE_AUDIOVISUEL_SS_3'],
        'traductions - interpretes de conferences' => ['TS_TRADUCTION_INTERPRETARIAT_SS_1'],
        'apps et sites web evenementiels' => ['TS_DIGITAL_HYBRIDE_SS_1', 'TS_DIGITAL_HYBRIDE_SS_2'],
    ];

    /**
     * @return array{codes: list<string>, warnings: list<string>}
     */
    public function prestations(string $typePrestataire): array
    {
        $typePrestataire = trim($typePrestataire);
        if ('' === $typePrestataire) {
            return ['codes' => [], 'warnings' => ['type_prestataire_absent']];
        }
        $code = self::PRESTATIONS[self::normalize($typePrestataire)] ?? null;
        if (null === $code) {
            return ['codes' => [], 'warnings' => ['type_prestataire_non_mappe']];
        }

        return ['codes' => [$code], 'warnings' => []];
    }

    /**
     * Sous-prestations déduites du type legacy — vide sans équivalent fin
     * (la famille seule suffit alors, pas un avertissement).
     *
     * @return list<string>
     */
    public function sousPrestations(string $typePrestataire): array
    {
        return self::SOUS_PRESTATIONS[self::normalize(trim($typePrestataire))] ?? [];
    }

    private static function normalize(string $value): string
    {
        $value = str_replace(['’', '‘', '…'], ["'", "'", '...'], mb_strtolower(trim($value)));
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT', $value);

        return preg_replace('/\s+/', ' ', false === $ascii ? $value : $ascii) ?? $value;
    }
}
