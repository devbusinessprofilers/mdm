<?php

declare(strict_types=1);

namespace App\Pim\Entity;

/**
 * Horaires d'ouverture par jour — {jour: {ouverture: 'HH:MM', fermeture: 'HH:MM'}}.
 * Source de vérité unique des horaires ; l'amplitude globale (première
 * ouverture, dernière fermeture) n'est plus stockée, elle est dérivée ici pour
 * les contrats sortants (payload marketplace, API portail, complétude).
 */
final class HorairesJours
{
    private function __construct()
    {
    }

    /**
     * Nettoie une saisie : heures vides écartées, jours sans heure supprimés,
     * heures zéro-paddées (« 9:00 » → « 09:00 ») pour que les comparaisons
     * lexicographiques min/max restent correctes.
     *
     * @param array<array-key, array{ouverture?: ?string, fermeture?: ?string}>|null $value
     *
     * @return array<string, array{ouverture: ?string, fermeture: ?string}>|null
     */
    public static function nettoie(?array $value): ?array
    {
        $nettoye = [];
        foreach ($value ?? [] as $jour => $heures) {
            $ouverture = self::heure($heures['ouverture'] ?? null);
            $fermeture = self::heure($heures['fermeture'] ?? null);
            if (null !== $ouverture || null !== $fermeture) {
                $nettoye[(string) $jour] = ['ouverture' => $ouverture, 'fermeture' => $fermeture];
            }
        }

        return [] === $nettoye ? null : $nettoye;
    }

    /**
     * Amplitude dérivée : première ouverture et dernière fermeture de la
     * semaine.
     *
     * @param array<string, array{ouverture?: ?string, fermeture?: ?string}>|null $jours
     *
     * @return array{ouverture: ?string, fermeture: ?string}
     */
    public static function amplitude(?array $jours): array
    {
        $ouvertures = [];
        $fermetures = [];
        foreach ($jours ?? [] as $heures) {
            $ouvertures[] = self::heure($heures['ouverture'] ?? null);
            $fermetures[] = self::heure($heures['fermeture'] ?? null);
        }
        $ouvertures = array_filter($ouvertures, static fn (?string $h): bool => null !== $h);
        $fermetures = array_filter($fermetures, static fn (?string $h): bool => null !== $h);

        return [
            'ouverture' => [] === $ouvertures ? null : min($ouvertures),
            'fermeture' => [] === $fermetures ? null : max($fermetures),
        ];
    }

    private static function heure(?string $value): ?string
    {
        $value = trim((string) $value);
        if ('' === $value) {
            return null;
        }
        if (1 === preg_match('/^(\d{1,2}):(\d{2})$/', $value, $m)) {
            return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }

        return $value;
    }
}
