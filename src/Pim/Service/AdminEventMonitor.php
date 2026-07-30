<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Shared\Outbox\OutboxRepository;
use Doctrine\DBAL\Connection;

final readonly class AdminEventMonitor
{
    private const QUEUES = ['pim', 'dam', 'etl', 'enrichment', 'mail', 'failed'];

    public function __construct(
        private Connection $connection,
        private OutboxRepository $outbox,
        private AdminEventCatalog $catalog,
    ) {
    }

    /**
     * @return array{
     *     available: bool,
     *     error: string|null,
     *     outbox: array<string, int>,
     *     queues: array<string, int>,
     *     recent: list<array{id: string, label: string, type: string, status: string, attempts: int, occurredAt: string, processedAt: string|null, error: string|null}>
     * }
     */
    public function snapshot(): array
    {
        try {
            $queues = array_fill_keys(self::QUEUES, 0);
            foreach ($this->connection->fetchAllAssociative('SELECT queue_name, COUNT(*) AS total FROM messenger_messages GROUP BY queue_name') as $row) {
                $queues[(string) $row['queue_name']] = (int) $row['total'];
            }

            $recent = [];
            foreach ($this->connection->fetchAllAssociative(
                <<<'SQL'
                    SELECT o.id, o.message_type, o.status, o.attempts, o.occurred_at, o.last_error, p.processed_at
                    FROM outbox_message o
                    LEFT JOIN processed_message p ON p.event_id = o.id
                    ORDER BY o.occurred_at DESC
                    LIMIT 20
                    SQL,
            ) as $row) {
                $type = (string) $row['message_type'];
                $recent[] = [
                    'id' => (string) $row['id'],
                    'label' => $this->catalog->labelFor($type),
                    'type' => $type,
                    'status' => $this->observedStatus((string) $row['status'], $row['processed_at']),
                    'attempts' => (int) $row['attempts'],
                    'occurredAt' => (string) $row['occurred_at'],
                    'processedAt' => null === $row['processed_at'] ? null : (string) $row['processed_at'],
                    'error' => null === $row['last_error'] ? null : (string) $row['last_error'],
                ];
            }

            return [
                'available' => true,
                'error' => null,
                'outbox' => $this->outbox->countByStatus(),
                'queues' => $queues,
                'recent' => $recent,
            ];
        } catch (\Throwable $error) {
            return [
                'available' => false,
                'error' => $error->getMessage(),
                'outbox' => [],
                'queues' => [],
                'recent' => [],
            ];
        }
    }

    private function observedStatus(string $outboxStatus, mixed $processedAt): string
    {
        if ('failed' === $outboxStatus) {
            return 'Échec de publication';
        }
        if (null !== $processedAt) {
            return 'Traité';
        }
        if ('pending' === $outboxStatus) {
            return 'En attente outbox';
        }

        return 'Publié, traitement attendu';
    }
}
