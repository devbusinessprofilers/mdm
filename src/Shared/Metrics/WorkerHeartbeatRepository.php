<?php

declare(strict_types=1);

namespace App\Shared\Metrics;

use Doctrine\DBAL\Connection;

/**
 * Lecture des heartbeats pour /admin/performance. Tous les âges sont calculés
 * en SQL contre NOW() : les écritures utilisent l'horloge MySQL, la
 * comparaison reste donc dans le même référentiel quel que soit le fuseau PHP.
 */
final readonly class WorkerHeartbeatRepository
{
    public function __construct(private Connection $connection)
    {
    }

    /**
     * Instances vues dans les dernières 24 h, la plus récente d'abord pour
     * chaque nom de worker.
     *
     * @return list<array{
     *     worker_key: string, worker_name: string, hostname: string, pid: int,
     *     transports: list<string>, status: string, uptime_s: int,
     *     last_seen_ago_s: int, memory_bytes: int, memory_peak_bytes: int,
     *     busy_ms_total: int, handled_total: int, failed_total: int,
     *     retried_total: int, current_message_class: ?string,
     *     current_message_since_s: ?int,
     *     message_stats: array<string, array{count: int, ms_sum: float, failed: int}>,
     *     transport_stats: array<string, array{handled: int, ms_sum: float}>,
     * }>
     */
    public function recents(): array
    {
        $rows = $this->connection->fetchAllAssociative(<<<'SQL'
            SELECT worker_key, worker_name, hostname, pid, transports, status,
                   TIMESTAMPDIFF(SECOND, started_at, NOW()) AS uptime_s,
                   TIMESTAMPDIFF(SECOND, last_seen_at, NOW()) AS last_seen_ago_s,
                   memory_bytes, memory_peak_bytes, busy_ms_total,
                   handled_total, failed_total, retried_total,
                   current_message_class,
                   TIMESTAMPDIFF(SECOND, current_message_since, NOW()) AS current_message_since_s,
                   message_stats, transport_stats
            FROM worker_heartbeat
            WHERE last_seen_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY worker_name, last_seen_at DESC
            SQL);

        return array_map(static fn (array $row): array => [
            'worker_key' => (string) $row['worker_key'],
            'worker_name' => (string) $row['worker_name'],
            'hostname' => (string) $row['hostname'],
            'pid' => (int) $row['pid'],
            'transports' => array_values(array_map(strval(...), (array) json_decode((string) $row['transports'], true))),
            'status' => (string) $row['status'],
            'uptime_s' => max(0, (int) $row['uptime_s']),
            'last_seen_ago_s' => max(0, (int) $row['last_seen_ago_s']),
            'memory_bytes' => (int) $row['memory_bytes'],
            'memory_peak_bytes' => (int) $row['memory_peak_bytes'],
            'busy_ms_total' => (int) $row['busy_ms_total'],
            'handled_total' => (int) $row['handled_total'],
            'failed_total' => (int) $row['failed_total'],
            'retried_total' => (int) $row['retried_total'],
            'current_message_class' => null !== $row['current_message_class'] ? (string) $row['current_message_class'] : null,
            'current_message_since_s' => null !== $row['current_message_since_s'] ? max(0, (int) $row['current_message_since_s']) : null,
            'message_stats' => (array) json_decode((string) $row['message_stats'], true),
            'transport_stats' => (array) json_decode((string) $row['transport_stats'], true),
        ], $rows);
    }

    /** @return int lignes supprimées */
    public function purge(int $heures = 48, int $lot = 5000): int
    {
        $total = 0;
        do {
            $supprimees = (int) $this->connection->executeStatement(
                'DELETE FROM worker_heartbeat WHERE last_seen_at < DATE_SUB(NOW(), INTERVAL :heures HOUR) LIMIT '.$lot,
                ['heures' => $heures],
            );
            $total += $supprimees;
        } while ($supprimees === $lot);

        return $total;
    }
}
