<?php

declare(strict_types=1);

namespace App\Pim\Service;

/**
 * Parseur volontairement conservateur du tag OSM `opening_hours` : seuls les
 * motifs simples et sans ambiguïté sont traduits — `24/7`, et des règles
 * « jours plage-horaire » (`Mo-Fr 09:00-18:00`, `Mo,We,Fr 10:00-19:00`,
 * plusieurs règles séparées par `;`). Tout le reste (mois, jours fériés PH/SH,
 * `off`/`closed`, plages multiples dans une règle, horaires passant minuit,
 * jour défini deux fois) rend null : mieux vaut aucune suggestion qu'une
 * suggestion fausse.
 */
final class HorairesOsm
{
    /** Jour OSM → code LOV DISPO_JOUR_OUVERTURE (Lundi..Dimanche). */
    private const JOURS = ['Mo' => 1, 'Tu' => 2, 'We' => 3, 'Th' => 4, 'Fr' => 5, 'Sa' => 6, 'Su' => 7];

    /**
     * @return array{jours: list<string>, horaires: array<string, array{ouverture: string, fermeture: string}>}|null
     *         jours = codes DISPO_JOUR_OUVERTURE_1..7 triés ; horaires vide
     *         pour `24/7` (les jours sont sûrs, l'amplitude ne l'est pas)
     */
    public static function parser(string $openingHours): ?array
    {
        $openingHours = trim($openingHours);
        if ('' === $openingHours) {
            return null;
        }
        if ('24/7' === $openingHours) {
            return ['jours' => self::codes(range(1, 7)), 'horaires' => []];
        }
        $horaires = [];
        foreach (explode(';', $openingHours) as $regle) {
            $regle = trim($regle);
            if ('' === $regle) {
                continue;
            }
            if (1 !== preg_match('/^([A-Za-z,\- ]+?)\s+(\d{1,2}:\d{2})-(\d{1,2}:\d{2})$/', $regle, $m)) {
                return null;
            }
            $jours = self::jours($m[1]);
            $ouverture = self::heure($m[2]);
            $fermeture = self::heure($m[3]);
            // Une plage passant minuit ne se range pas dans « ouverture puis
            // fermeture le même jour » : hors périmètre.
            if (null === $jours || null === $ouverture || null === $fermeture || $fermeture <= $ouverture) {
                return null;
            }
            foreach ($jours as $jour) {
                if (isset($horaires[$jour])) {
                    return null;
                }
                $horaires[$jour] = ['ouverture' => $ouverture, 'fermeture' => $fermeture];
            }
        }
        if ([] === $horaires) {
            return null;
        }
        ksort($horaires);

        return [
            'jours' => self::codes(array_keys($horaires)),
            'horaires' => array_combine(self::codes(array_keys($horaires)), array_values($horaires)),
        ];
    }

    /**
     * « Mo-Fr » et « Mo,We,Fr » (combinables : « Mo-We,Fr ») → numéros de
     * jours 1..7. Une plage inversée (Su-Mo) enjambe la semaine : rejetée.
     *
     * @return list<int>|null
     */
    private static function jours(string $expression): ?array
    {
        $numeros = [];
        foreach (explode(',', str_replace(' ', '', $expression)) as $segment) {
            if (isset(self::JOURS[$segment])) {
                $numeros[] = self::JOURS[$segment];
                continue;
            }
            $bornes = explode('-', $segment);
            if (2 !== count($bornes) || !isset(self::JOURS[$bornes[0]], self::JOURS[$bornes[1]])
                || self::JOURS[$bornes[0]] > self::JOURS[$bornes[1]]) {
                return null;
            }
            $numeros = [...$numeros, ...range(self::JOURS[$bornes[0]], self::JOURS[$bornes[1]])];
        }

        return array_values(array_unique($numeros));
    }

    /** Heure `H:MM`/`HH:MM` normalisée `HH:MM`, bornée à la journée (null sinon). */
    private static function heure(string $valeur): ?string
    {
        [$heures, $minutes] = array_map(intval(...), explode(':', $valeur));
        if ($heures > 23 || $minutes > 59) {
            return null;
        }

        return sprintf('%02d:%02d', $heures, $minutes);
    }

    /**
     * Résumé lisible pour la colonne « Proposé » de l'arbitrage : jours
     * consécutifs à plage identique regroupés (« Lun-Ven 09:00-18:00, Sam
     * 10:00-17:00 »).
     *
     * @param array<string, array{ouverture: string, fermeture: string}> $horaires par code DISPO_JOUR_OUVERTURE_X
     */
    public static function resume(array $horaires): string
    {
        $groupes = [];
        /** @var array{int, int, string}|null $courant [jour de départ, jour de fin, plage] */
        $courant = null;
        for ($numero = 1; $numero <= 7; ++$numero) {
            $heures = $horaires['DISPO_JOUR_OUVERTURE_'.$numero] ?? null;
            $plage = null === $heures ? null : $heures['ouverture'].'-'.$heures['fermeture'];
            if (null !== $plage && null !== $courant && $courant[2] === $plage && $courant[1] === $numero - 1) {
                $courant[1] = $numero;
                continue;
            }
            if (null !== $courant) {
                $groupes[] = self::groupe($courant);
            }
            $courant = null === $plage ? null : [$numero, $numero, $plage];
        }
        if (null !== $courant) {
            $groupes[] = self::groupe($courant);
        }

        return implode(', ', $groupes);
    }

    /** @param array{int, int, string} $courant */
    private static function groupe(array $courant): string
    {
        $courts = [1 => 'Lun', 2 => 'Mar', 3 => 'Mer', 4 => 'Jeu', 5 => 'Ven', 6 => 'Sam', 7 => 'Dim'];
        [$depart, $fin, $plage] = $courant;

        return ($depart === $fin ? $courts[$depart] : $courts[$depart].'-'.$courts[$fin]).' '.$plage;
    }

    /**
     * @param list<int> $numeros
     *
     * @return list<string>
     */
    private static function codes(array $numeros): array
    {
        sort($numeros);

        return array_map(static fn (int $numero): string => 'DISPO_JOUR_OUVERTURE_'.$numero, $numeros);
    }
}
