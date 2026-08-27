<?php

declare(strict_types=1);

namespace App\Shared\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Battement de cœur d'une instance de worker (une ligne par processus,
 * clé hostname:pid). Écrit en DBAL pur par WorkerHeartbeatReporter — cette
 * entité ne sert qu'au schéma et à la lecture. Les compteurs sont des cumuls
 * depuis le démarrage du processus : les fenêtres glissantes se calculent par
 * différence entre échantillons perf_sample.
 */
#[ORM\Entity]
#[ORM\Table(name: 'worker_heartbeat')]
#[ORM\Index(name: 'IDX_HEARTBEAT_SEEN', columns: ['last_seen_at'])]
#[ORM\Index(name: 'IDX_HEARTBEAT_NAME_SEEN', columns: ['worker_name', 'last_seen_at'])]
final class WorkerHeartbeat
{
    public const STATUS_RUNNING = 'running';
    public const STATUS_STOPPED = 'stopped';

    /**
     * @param list<string>                                                     $transports
     * @param array<string, array{count: int, ms_sum: float, failed: int}>     $messageStats
     * @param array<string, array{handled: int, ms_sum: float}>                $transportStats
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 128)]
        private string $workerKey,
        #[ORM\Column(length: 64)]
        private string $workerName,
        #[ORM\Column(length: 64)]
        private string $hostname,
        #[ORM\Column(options: ['unsigned' => true])]
        private int $pid,
        #[ORM\Column(type: Types::JSON)]
        private array $transports,
        #[ORM\Column(length: 16)]
        private string $status,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $startedAt,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $lastSeenAt,
        #[ORM\Column(type: Types::BIGINT, options: ['unsigned' => true])]
        private int $memoryBytes,
        #[ORM\Column(type: Types::BIGINT, options: ['unsigned' => true])]
        private int $memoryPeakBytes,
        #[ORM\Column(type: Types::BIGINT, options: ['unsigned' => true])]
        private int $busyMsTotal,
        #[ORM\Column(options: ['unsigned' => true])]
        private int $handledTotal,
        #[ORM\Column(options: ['unsigned' => true])]
        private int $failedTotal,
        #[ORM\Column(options: ['unsigned' => true])]
        private int $retriedTotal,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $currentMessageClass = null,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
        private ?\DateTimeImmutable $currentMessageSince = null,
        #[ORM\Column(type: Types::JSON)]
        private array $messageStats = [],
        #[ORM\Column(type: Types::JSON)]
        private array $transportStats = [],
    ) {
    }

    public function workerKey(): string
    {
        return $this->workerKey;
    }
}
