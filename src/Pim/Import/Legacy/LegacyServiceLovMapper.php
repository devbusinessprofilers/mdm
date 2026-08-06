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

    private static function normalize(string $value): string
    {
        $value = str_replace(['’', '‘', '…'], ["'", "'", '...'], mb_strtolower(trim($value)));
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT', $value);

        return preg_replace('/\s+/', ' ', false === $ascii ? $value : $ascii) ?? $value;
    }
}
