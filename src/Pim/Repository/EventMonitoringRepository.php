<?php

declare(strict_types=1);

namespace App\Pim\Repository;

use Doctrine\DBAL\Connection;

final readonly class EventMonitoringRepository
{
    public function __construct(private Connection $connection) {}

    /** @return array<string, int> */
    public function queueCounts(): array
    {
        $counts = [];
        foreach ($this->connection->fetchAllAssociative('SELECT queue_name, COUNT(*) AS total FROM messenger_messages GROUP BY queue_name') as $row) {
            $counts[(string) $row['queue_name']] = (int) $row['total'];
        }

        return $counts;
    }

    /** @return list<array<string, mixed>> */
    public function recentEvents(int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));

        return $this->connection->fetchAllAssociative(<<<SQL
            SELECT o.id, o.message_type, o.status, o.attempts, o.occurred_at, o.last_error, p.processed_at
            FROM outbox_message o
            LEFT JOIN processed_message p ON p.event_id = o.id
            ORDER BY o.occurred_at DESC
            LIMIT {$limit}
            SQL);
    }
}
