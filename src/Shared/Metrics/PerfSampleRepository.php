<?php

declare(strict_types=1);

namespace App\Shared\Metrics;

use Doctrine\DBAL\Connection;

/** Lecture de la série temporelle perf_sample pour les graphiques. */
final readonly class PerfSampleRepository
{
    public function __construct(private Connection $connection)
    {
    }

    /**
     * Échantillons d'un type sur la fenêtre demandée, groupables par sujet,
     * horodatés en secondes d'âge relatif (référentiel NOW() MySQL, cohérent
     * avec les écritures).
     *
     * @return list<array{subject: string, age_s: int, metrics: array<string, mixed>}>
     */
    public function series(string $kind, int $fenetreMinutes): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT subject, TIMESTAMPDIFF(SECOND, sampled_at, NOW()) AS age_s, metrics
             FROM perf_sample
             WHERE kind = :kind AND sampled_at >= DATE_SUB(NOW(), INTERVAL :minutes MINUTE)
             ORDER BY subject, sampled_at',
            ['kind' => $kind, 'minutes' => $fenetreMinutes],
        );

        return array_map(static fn (array $row): array => [
            'subject' => (string) $row['subject'],
            'age_s' => max(0, (int) $row['age_s']),
            'metrics' => (array) json_decode((string) $row['metrics'], true),
        ], $rows);
    }

    /**
     * Filet de sécurité de la page : si aucun worker n'a échantillonné les
     * files depuis plus d'une minute (tous arrêtés), la page le fait.
     */
    public function topUpQueueSamples(): void
    {
        try {
            QueueSampler::sampleIfStale($this->connection, 60);
        } catch (\Throwable) {
            // La page d'admin ne doit pas tomber pour un échantillon manqué.
        }
    }

    /** @return int lignes supprimées */
    public function purge(int $jours = 7, int $lot = 5000): int
    {
        $total = 0;
        do {
            $supprimees = (int) $this->connection->executeStatement(
                'DELETE FROM perf_sample WHERE sampled_at < DATE_SUB(NOW(), INTERVAL :jours DAY) LIMIT '.$lot,
                ['jours' => $jours],
            );
            $total += $supprimees;
        } while ($supprimees === $lot);

        return $total;
    }
}
