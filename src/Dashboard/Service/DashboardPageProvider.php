<?php

declare(strict_types=1);

namespace App\Dashboard\Service;

use App\Dashboard\Entity\DashboardSnapshot;
use App\Dashboard\Repository\DashboardSnapshotRepository;
use App\Enrichment\Enum\SupportedLocale;
use App\Pim\Enum\TypeFiche;

final readonly class DashboardPageProvider
{
    private const int HISTORY_DAYS = 30;

    /*
     * Seuils de la carte « Caractères non traduits » : repère de charge, la
     * traduction coûtant ~20 $ le million de caractères. En régime normal le
     * flux automatique maintient la dette proche de zéro.
     */
    private const int TRADUCTIONS_ATTENTION = 50_000;
    private const int TRADUCTIONS_CRITIQUE = 250_000;

    public function __construct(private DashboardSnapshotRepository $snapshots)
    {
    }

    /** @return array<string, mixed> */
    public function page(): array
    {
        $snapshot = $this->snapshots->latest();
        if (null === $snapshot) {
            return ['snapshot' => null];
        }
        $payload = $snapshot->payload();

        return [
            'snapshot' => $snapshot,
            'computedAt' => $snapshot->computedAt(),
            'fiches' => $payload['fiches'],
            'completeness' => $payload['completeness'],
            'validation' => [
                'label' => $this->formatDuration($payload['validation']['avgSeconds'] ?? null),
                'sample' => $payload['validation']['sample'] ?? 0,
            ],
            'thisWeek' => $payload['thisWeek'],
            'typeLabels' => $this->typeLabels(),
            'countryRows' => $payload['countryByType'],
            'perUser' => [
                'created' => $this->withRatios($payload['perUser']['created'] ?? []),
                'fieldsUpdated' => $this->withRatios($payload['perUser']['fieldsUpdated'] ?? []),
                'validated' => $this->withRatios($payload['perUser']['validated'] ?? []),
            ],
            'storage' => $this->storage($payload['storage'] ?? null),
            'traductions' => $this->traductions($payload['translations'] ?? null),
            'fieldFill' => $this->fieldFill(),
            'sparklines' => $this->sparklines(),
            // Évolution de la complétude moyenne sur l'historique (30 j) —
            // nourrit la note « ±N pts » de la zone Santé du tableau de bord.
            'completudeDelta' => $this->completudeDelta(),
        ];
    }

    private function completudeDelta(): ?float
    {
        $history = $this->snapshots->dailyHistory(self::HISTORY_DAYS);
        if (count($history) < 2) {
            return null;
        }
        $premier = (float) ($history[0]->payload()['completeness']['avgGlobal'] ?? 0);
        $dernier = (float) ($history[count($history) - 1]->payload()['completeness']['avgGlobal'] ?? 0);

        return round($dernier - $premier, 1);
    }

    /**
     * @param array<string, mixed>|null $storage
     *
     * @return array{total: string, images: string, documents: string, renditions: string}|null
     */
    private function storage(?array $storage): ?array
    {
        if (null === $storage) {
            return null;
        }
        /** @var array<string, array{count: int, bytes: int}> $byKind */
        $byKind = $storage['byKind'] ?? [];
        /** @var array{count: int, bytes: int} $renditions */
        $renditions = $storage['renditions'] ?? ['count' => 0, 'bytes' => 0];

        $totalBytes = (int) ($storage['totalBytes'] ?? 0);
        $parts = [
            ['libelle' => 'Images', 'teinte' => 'bg-primary', 'count' => (int) ($byKind['image']['count'] ?? 0), 'bytes' => (int) ($byKind['image']['bytes'] ?? 0)],
            ['libelle' => 'Documents', 'teinte' => 'bg-primary-3', 'count' => (int) ($byKind['document']['count'] ?? 0), 'bytes' => (int) ($byKind['document']['bytes'] ?? 0)],
            ['libelle' => 'Variantes générées', 'teinte' => 'bg-peach', 'count' => (int) $renditions['count'], 'bytes' => (int) $renditions['bytes']],
        ];

        return [
            'total' => $this->formatBytes($totalBytes),
            'images' => sprintf('%s (%d)', $this->formatBytes($byKind['image']['bytes'] ?? 0), $byKind['image']['count'] ?? 0),
            'documents' => sprintf('%s (%d)', $this->formatBytes($byKind['document']['bytes'] ?? 0), $byKind['document']['count'] ?? 0),
            'renditions' => sprintf('%s (%d)', $this->formatBytes($renditions['bytes']), $renditions['count']),
            // Barre segmentée de la zone Médias : part de chaque famille dans
            // le stockage, libellé avec volume lisible.
            'mediasTotal' => $parts[0]['count'] + $parts[1]['count'],
            'segments' => array_map(
                fn (array $part): array => [
                    'libelle' => sprintf('%s · %s', $part['libelle'], $this->formatBytes($part['bytes'])),
                    'teinte' => $part['teinte'],
                    'part' => $totalBytes > 0 ? round(100 * $part['bytes'] / $totalBytes, 1) : 0,
                ],
                $parts,
            ),
        ];
    }

    /**
     * Vue traductions (fiches publiées) : carte de la zone Files + tuiles par
     * langue de la zone Santé. Null tant qu'aucun snapshot ne porte la clé
     * (anciens snapshots d'avant la fonctionnalité).
     *
     * @param array{
     *     byLocale: list<array{locale: string, total: int, disponibles: int, enAttente: int, enErreur: int, caracteres: int}>,
     *     pending: array{champs: int, caracteres: int, fiches: int},
     * }|null $translations
     *
     * @return array{
     *     caracteres: int,
     *     caracteresCompact: string,
     *     champs: int,
     *     fiches: int,
     *     severite: string,
     *     parLangue: list<array{label: string, pct: float|null, aTraduire: int, enErreur: int}>,
     * }|null
     */
    private function traductions(?array $translations): ?array
    {
        if (null === $translations) {
            return null;
        }
        $byLocale = [];
        foreach ($translations['byLocale'] as $ligne) {
            $byLocale[$ligne['locale']] = $ligne;
        }
        $parLangue = [];
        // L'ordre d'affichage suit targets() ; une langue sans aucune ligne
        // planifiée apparaît quand même, à « — ».
        foreach (SupportedLocale::targets() as $locale) {
            $ligne = $byLocale[$locale->value] ?? null;
            $total = (int) ($ligne['total'] ?? 0);
            $disponibles = (int) ($ligne['disponibles'] ?? 0);
            $parLangue[] = [
                'label' => $locale->label(),
                'pct' => $total > 0 ? round(100 * $disponibles / $total, 1) : null,
                'aTraduire' => $total - $disponibles,
                'enErreur' => (int) ($ligne['enErreur'] ?? 0),
            ];
        }
        $caracteres = $translations['pending']['caracteres'];

        return [
            'caracteres' => $caracteres,
            'caracteresCompact' => $this->formatCompte($caracteres),
            'champs' => $translations['pending']['champs'],
            'fiches' => $translations['pending']['fiches'],
            'severite' => match (true) {
                $caracteres > self::TRADUCTIONS_CRITIQUE => 'critique',
                $caracteres > self::TRADUCTIONS_ATTENTION => 'attention',
                default => 'normale',
            },
            'parLangue' => $parLangue,
        ];
    }

    /** Nombre compact pour les cartes : 999, « 12,5 k », « 1,2 M ». */
    private function formatCompte(int $valeur): string
    {
        if ($valeur >= 1_000_000) {
            return number_format($valeur / 1_000_000, 1, ',', ' ').' M';
        }
        if ($valeur >= 10_000) {
            return number_format((int) round($valeur / 1000), 0, ',', ' ').' k';
        }
        if ($valeur >= 1_000) {
            return number_format($valeur / 1000, 1, ',', ' ').' k';
        }

        return (string) $valeur;
    }

    /** @return array{computedAt: \DateTimeImmutable, perType: array<string, array{fiches: int, worstFields: list<array{code: string, label: string, applicable: int, filled: int, rate: float}>}>}|null */
    private function fieldFill(): ?array
    {
        $snapshot = $this->snapshots->latest(DashboardSnapshot::KIND_FIELD_FILL);
        if (null === $snapshot) {
            return null;
        }
        /** @var array<string, array{fiches: int, worstFields: list<array{code: string, label: string, applicable: int, filled: int, rate: float}>}> $perType */
        $perType = $snapshot->payload()['perType'] ?? [];

        return ['computedAt' => $snapshot->computedAt(), 'perType' => $perType];
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 ** 3) {
            return number_format($bytes / 1024 ** 3, 1, ',', ' ').' Go';
        }
        if ($bytes >= 1024 ** 2) {
            return number_format($bytes / 1024 ** 2, 1, ',', ' ').' Mo';
        }

        return number_format((int) ceil($bytes / 1024), 0, ',', ' ').' Ko';
    }

    /** @return array<string, string> */
    private function typeLabels(): array
    {
        $labels = [];
        foreach (TypeFiche::cases() as $type) {
            $labels[$type->value] = $type->libelle();
        }

        return $labels;
    }

    private function formatDuration(?int $seconds): ?string
    {
        if (null === $seconds) {
            return null;
        }
        if ($seconds < 3600) {
            return sprintf('%d min', intdiv($seconds, 60));
        }
        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        if ($days > 0) {
            return sprintf('%d j %d h', $days, $hours);
        }

        return sprintf('%d h %02d min', $hours, intdiv($seconds % 3600, 60));
    }

    /**
     * @param list<array{actor: string, count: int}> $rows
     *
     * @return list<array{actor: string, count: int, ratio: float}>
     */
    private function withRatios(array $rows): array
    {
        $max = 0;
        foreach ($rows as $row) {
            $max = max($max, $row['count']);
        }

        return array_map(
            static fn (array $row): array => $row + [
                'ratio' => 0 === $max ? 0.0 : round(100 * $row['count'] / $max, 1),
            ],
            $rows,
        );
    }

    /** @return list<array{label: string, points: string, first: string, last: string}> */
    private function sparklines(): array
    {
        $history = $this->snapshots->dailyHistory(self::HISTORY_DAYS);
        if (count($history) < 2) {
            return [];
        }
        $series = [
            ['label' => 'Total de fiches', 'values' => []],
            ['label' => 'Fiches publiées', 'values' => []],
            ['label' => 'Complétude moyenne (%)', 'values' => []],
        ];
        foreach ($history as $snapshot) {
            $payload = $snapshot->payload();
            $series[0]['values'][] = (float) ($payload['fiches']['total'] ?? 0);
            $series[1]['values'][] = (float) ($payload['fiches']['published'] ?? 0);
            $series[2]['values'][] = (float) ($payload['completeness']['avgGlobal'] ?? 0);
        }

        return array_map(
            fn (array $serie): array => [
                'label' => $serie['label'],
                'points' => $this->polylinePoints($serie['values']),
                'first' => $this->formatValue($serie['values'][0]),
                'last' => $this->formatValue($serie['values'][count($serie['values']) - 1]),
            ],
            $series,
        );
    }

    /** @param non-empty-list<float> $values */
    private function polylinePoints(array $values): string
    {
        $min = min($values);
        $max = max($values);
        $range = $max - $min;
        $count = count($values);
        $points = [];
        foreach ($values as $i => $value) {
            $x = round(100 * $i / ($count - 1), 2);
            $y = 0.0 === $range
                ? 15.0
                : round(29 - 28 * ($value - $min) / $range, 2);
            $points[] = $x.','.$y;
        }

        return implode(' ', $points);
    }

    private function formatValue(float $value): string
    {
        return $value === floor($value)
            ? (string) (int) $value
            : number_format($value, 1, ',', ' ');
    }
}
