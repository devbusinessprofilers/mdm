<?php

declare(strict_types=1);

namespace App\Shared\Outbox;

use Doctrine\DBAL\Connection;

final readonly class OutboxRepository
{
    private const MAX_ATTEMPTS = 5;

    public function __construct(private Connection $connection)
    {
    }

    public function claimNext(string $workerId, int $leaseSeconds = 300): ?OutboxRecord
    {
        return $this->connection->transactional(function () use ($workerId, $leaseSeconds): ?OutboxRecord {
            $now = new \DateTimeImmutable();
            $row = $this->connection->fetchAssociative(
                <<<'SQL'
                    SELECT id, message_type, body, headers, attempts
                    FROM outbox_message
                    WHERE status = :status
                      AND available_at <= :now
                      AND (locked_at IS NULL OR locked_at < :expired_at)
                    ORDER BY occurred_at ASC
                    LIMIT 1
                    FOR UPDATE SKIP LOCKED
                    SQL,
                [
                    'status' => OutboxStatus::Pending->value,
                    'now' => $this->formatDate($now),
                    'expired_at' => $this->formatDate($now->modify(sprintf('-%d seconds', $leaseSeconds))),
                ],
            );

            if (false === $row) {
                return null;
            }

            $this->connection->update('outbox_message', [
                'locked_at' => $this->formatDate($now),
                'locked_by' => $workerId,
            ], ['id' => $row['id']]);

            return new OutboxRecord(
                id: (string) $row['id'],
                messageType: (string) $row['message_type'],
                body: (string) $row['body'],
                headers: (string) $row['headers'],
                attempts: (int) $row['attempts'],
                lockedBy: $workerId,
            );
        });
    }

    public function markPublished(OutboxRecord $record): void
    {
        $affected = $this->connection->update('outbox_message', [
            'status' => OutboxStatus::Published->value,
            'published_at' => $this->formatDate(new \DateTimeImmutable()),
            'locked_at' => null,
            'locked_by' => null,
            'last_error' => null,
        ], [
            'id' => $record->id,
            'status' => OutboxStatus::Pending->value,
            'locked_by' => $record->lockedBy,
        ]);

        if (1 !== $affected) {
            throw new \RuntimeException(sprintf('Outbox message "%s" is no longer owned by this worker.', $record->id));
        }
    }

    public function recordFailure(OutboxRecord $record, \Throwable $error): void
    {
        $attempts = $record->attempts + 1;
        $status = $attempts >= self::MAX_ATTEMPTS ? OutboxStatus::Failed : OutboxStatus::Pending;
        $delays = [5, 10, 20, 40, 60];

        $affected = $this->connection->update('outbox_message', [
            'status' => $status->value,
            'attempts' => $attempts,
            'available_at' => $this->formatDate(new \DateTimeImmutable(sprintf('+%d seconds', $delays[$attempts - 1]))),
            'locked_at' => null,
            'locked_by' => null,
            'last_error' => substr(sprintf('%s: %s', $error::class, $error->getMessage()), 0, 4000),
        ], [
            'id' => $record->id,
            'status' => OutboxStatus::Pending->value,
            'locked_by' => $record->lockedBy,
        ]);

        if (1 !== $affected) {
            throw new \RuntimeException(sprintf('Could not record failure for outbox message "%s".', $record->id));
        }
    }

    /**
     * @return array<string, int>
     */
    public function countByStatus(): array
    {
        $counts = [
            OutboxStatus::Pending->value => 0,
            OutboxStatus::Published->value => 0,
            OutboxStatus::Failed->value => 0,
        ];

        foreach ($this->connection->fetchAllAssociative('SELECT status, COUNT(*) AS total FROM outbox_message GROUP BY status') as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findFailed(?string $id = null): array
    {
        $sql = 'SELECT id, message_type, attempts, occurred_at, available_at, last_error FROM outbox_message WHERE status = :status';
        $params = ['status' => OutboxStatus::Failed->value];

        if (null !== $id) {
            $sql .= ' AND id = :id';
            $params['id'] = $id;
        }

        $sql .= ' ORDER BY occurred_at ASC';

        return $this->connection->fetchAllAssociative($sql, $params);
    }

    public function retryFailed(string $id): bool
    {
        return 1 === $this->connection->update('outbox_message', [
            'status' => OutboxStatus::Pending->value,
            'attempts' => 0,
            'available_at' => $this->formatDate(new \DateTimeImmutable()),
            'locked_at' => null,
            'locked_by' => null,
            'last_error' => null,
        ], [
            'id' => $id,
            'status' => OutboxStatus::Failed->value,
        ]);
    }

    /**
     * @return array{outbox: int, receipts: int}
     */
    public function purge(\DateTimeImmutable $publishedBefore, \DateTimeImmutable $processedBefore): array
    {
        return [
            'outbox' => (int) $this->connection->executeStatement(
                'DELETE FROM outbox_message WHERE status = :status AND published_at < :before',
                ['status' => OutboxStatus::Published->value, 'before' => $this->formatDate($publishedBefore)],
            ),
            'receipts' => (int) $this->connection->executeStatement(
                'DELETE FROM processed_message WHERE processed_at < :before',
                ['before' => $this->formatDate($processedBefore)],
            ),
        ];
    }

    private function formatDate(\DateTimeImmutable $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }
}
