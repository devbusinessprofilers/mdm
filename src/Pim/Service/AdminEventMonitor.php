<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Repository\EventMonitoringRepository;
use App\Shared\Outbox\OutboxRepository;
use App\Shared\Repository\FilesMessengerRepository;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final readonly class AdminEventMonitor
{
    private const QUEUES = ['pim', 'dam', 'etl', 'enrichment', 'mail', 'failed'];
    private const CACHE_KEY = 'admin_event_monitor.snapshot';
    private const CACHE_TTL = 10;

    public function __construct(
        private EventMonitoringRepository $events,
        private FilesMessengerRepository $files,
        private OutboxRepository $outbox,
        private AdminEventCatalog $catalog,
        private CacheInterface $cache,
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
    public function snapshot(bool $fresh = false): array
    {
        if ($fresh) {
            $this->cache->delete(self::CACHE_KEY);
        }

        return $this->cache->get(self::CACHE_KEY, function (ItemInterface $item): array {
            $item->expiresAfter(self::CACHE_TTL);

            return $this->computeSnapshot();
        });
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
    private function computeSnapshot(): array
    {
        try {
            $queues = array_fill_keys(self::QUEUES, 0);
            foreach ($this->files->totalParFile() as $queue => $total) {
                $queues[$queue] = $total;
            }

            $recent = [];
            foreach ($this->events->recentEvents() as $row) {
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
