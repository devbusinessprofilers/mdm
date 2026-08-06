<?php

declare(strict_types=1);

namespace App\Dashboard\Service;

use App\Dashboard\Entity\DashboardSnapshot;
use App\Dashboard\Repository\DashboardSnapshotRepository;
use App\Pim\Enum\TypeFiche;

final readonly class DashboardPageProvider
{
    private const int HISTORY_DAYS = 30;

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
            'sparklines' => $this->sparklines(),
        ];
    }

    /** @return array<string, string> */
    private function typeLabels(): array
    {
        $labels = [];
        foreach (TypeFiche::cases() as $type) {
            $labels[$type->value] = ucfirst(str_replace('_', ' ', $type->value));
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
