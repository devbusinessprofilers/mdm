<?php

declare(strict_types=1);

namespace App\Shared\Metrics;

use App\Shared\Entity\PerfSample;
use App\Shared\Entity\WorkerHeartbeat;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;

/**
 * État vivant d'un processus worker, poussé en BDD pour /admin/performance :
 * les conteneurs n'exposant ni docker socket ni /proc croisé, MySQL est le
 * seul canal partagé. Compteurs accumulés en mémoire, flush throttlé (un
 * upsert toutes les FLUSH_INTERVAL_S au plus) ; toutes les écritures en DBAL
 * pur — l'EntityManager d'un worker peut être fermé après un handler en échec
 * — et sous try/catch : un incident BDD ne doit jamais tuer un worker.
 *
 * Les horodatages persistés viennent de NOW() MySQL (jamais de l'horloge PHP)
 * pour que les âges se calculent en SQL sans écart de fuseau ; l'horloge
 * injectée ne sert qu'au throttling relatif.
 */
final class WorkerHeartbeatReporter
{
    private const FLUSH_INTERVAL_S = 5.0;
    // Un message long gèle la boucle d'événements du worker (plus aucun beat
    // pendant handle()) : on force un flush au début du traitement, borné à
    // un par seconde pour ne pas écrire à chaque message des files rapides.
    private const MESSAGE_FLUSH_INTERVAL_S = 1.0;
    private const SAMPLE_INTERVAL_S = 60.0;

    private ?string $workerKey = null;
    private string $workerName = '';
    private string $hostname = '';
    private int $pid = 0;
    /** @var list<string> */
    private array $transports = [];
    private string $status = WorkerHeartbeat::STATUS_RUNNING;
    private float $lastFlushAt = 0.0;
    private float $lastSampleAt = 0.0;
    private float $busyMsTotal = 0.0;
    private int $handledTotal = 0;
    private int $failedTotal = 0;
    private int $retriedTotal = 0;
    private ?string $currentMessageClass = null;
    private float $currentMessageStartedAt = 0.0;
    /** @var array<string, array{count: int, ms_sum: float, failed: int}> */
    private array $messageStats = [];
    /** @var array<string, array{handled: int, ms_sum: float}> */
    private array $transportStats = [];

    public function __construct(
        private readonly Connection $connection,
        private readonly WorkerNameResolver $names,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @param array<string> $transports
     */
    public function start(array $transports): void
    {
        $this->hostname = substr(gethostname() ?: 'worker', 0, 64);
        $this->pid = getmypid() ?: 0;
        $this->workerKey = substr(sprintf('%s:%d', $this->hostname, $this->pid), 0, 128);
        $this->transports = array_values($transports);
        $this->workerName = substr($this->names->resolve($this->transports), 0, 64);
        $this->status = WorkerHeartbeat::STATUS_RUNNING;
        $this->lastFlushAt = 0.0;
        $this->lastSampleAt = $this->nowS();
        $this->busyMsTotal = 0.0;
        $this->handledTotal = $this->failedTotal = $this->retriedTotal = 0;
        $this->currentMessageClass = null;
        $this->messageStats = [];
        $this->transportStats = [];
        $this->beat(force: true);
    }

    public function messageStarted(string $class): void
    {
        if (null === $this->workerKey) {
            return;
        }
        $this->currentMessageClass = substr($class, 0, 255);
        $this->currentMessageStartedAt = $this->nowS();
        if ($this->currentMessageStartedAt - $this->lastFlushAt >= self::MESSAGE_FLUSH_INTERVAL_S) {
            $this->beat(force: true);
        }
    }

    public function recordMessage(string $class, string $transport, string $outcome, float $seconds, int $count = 1): void
    {
        if (null === $this->workerKey) {
            return;
        }
        $ms = max(0.0, $seconds * 1000);
        $this->busyMsTotal += $ms;
        match ($outcome) {
            'failed' => $this->failedTotal += $count,
            'retried' => $this->retriedTotal += $count,
            default => $this->handledTotal += $count,
        };
        $stats = $this->messageStats[$class] ?? ['count' => 0, 'ms_sum' => 0.0, 'failed' => 0];
        $stats['count'] += $count;
        $stats['ms_sum'] += $ms;
        if ('failed' === $outcome) {
            ++$stats['failed'];
        }
        $this->messageStats[$class] = $stats;
        $tStats = $this->transportStats[$transport] ?? ['handled' => 0, 'ms_sum' => 0.0];
        $tStats['handled'] += $count;
        $tStats['ms_sum'] += $ms;
        $this->transportStats[$transport] = $tStats;
        $this->currentMessageClass = null;
        $this->beat();
    }

    public function stop(): void
    {
        if (null === $this->workerKey) {
            return;
        }
        $this->status = WorkerHeartbeat::STATUS_STOPPED;
        $this->currentMessageClass = null;
        $this->beat(force: true);
    }

    public function beat(bool $force = false): void
    {
        if (null === $this->workerKey) {
            return;
        }
        $now = $this->nowS();
        if (!$force && $now - $this->lastFlushAt < self::FLUSH_INTERVAL_S) {
            return;
        }
        // Avancé avant l'écriture : une BDD en panne ne doit pas être
        // re-sollicitée à chaque événement de la boucle du worker.
        $this->lastFlushAt = $now;
        try {
            $this->upsertHeartbeat($now);
            if ($now - $this->lastSampleAt >= self::SAMPLE_INTERVAL_S) {
                $this->lastSampleAt = $now;
                $this->insertWorkerSample();
                $this->maybeInsertQueueSamples();
            }
        } catch (\Throwable) {
            // Jamais de propagation : le monitoring est sacrifiable, pas le worker.
        }
    }

    private function upsertHeartbeat(float $now): void
    {
        $sinceS = null !== $this->currentMessageClass
            ? max(0, (int) round($now - $this->currentMessageStartedAt))
            : null;
        $this->connection->executeStatement(<<<'SQL'
            INSERT INTO worker_heartbeat (
                worker_key, worker_name, hostname, pid, transports, status,
                started_at, last_seen_at, memory_bytes, memory_peak_bytes,
                busy_ms_total, handled_total, failed_total, retried_total,
                current_message_class, current_message_since, message_stats, transport_stats
            ) VALUES (
                :worker_key, :worker_name, :hostname, :pid, :transports, :status,
                NOW(), NOW(), :memory_bytes, :memory_peak_bytes,
                :busy_ms_total, :handled_total, :failed_total, :retried_total,
                :current_message_class,
                CASE WHEN :current_message_since_s IS NULL THEN NULL
                     ELSE DATE_SUB(NOW(), INTERVAL :current_message_since_s SECOND) END,
                :message_stats, :transport_stats
            )
            ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                last_seen_at = VALUES(last_seen_at),
                memory_bytes = VALUES(memory_bytes),
                memory_peak_bytes = VALUES(memory_peak_bytes),
                busy_ms_total = VALUES(busy_ms_total),
                handled_total = VALUES(handled_total),
                failed_total = VALUES(failed_total),
                retried_total = VALUES(retried_total),
                current_message_class = VALUES(current_message_class),
                current_message_since = VALUES(current_message_since),
                message_stats = VALUES(message_stats),
                transport_stats = VALUES(transport_stats)
            SQL, [
            'worker_key' => $this->workerKey,
            'worker_name' => $this->workerName,
            'hostname' => $this->hostname,
            'pid' => $this->pid,
            'transports' => json_encode($this->transports, \JSON_THROW_ON_ERROR),
            'status' => $this->status,
            'memory_bytes' => memory_get_usage(true),
            'memory_peak_bytes' => memory_get_peak_usage(true),
            'busy_ms_total' => (int) round($this->busyMsTotal),
            'handled_total' => $this->handledTotal,
            'failed_total' => $this->failedTotal,
            'retried_total' => $this->retriedTotal,
            'current_message_class' => $this->currentMessageClass,
            'current_message_since_s' => $sinceS,
            'message_stats' => json_encode($this->messageStats, \JSON_THROW_ON_ERROR),
            'transport_stats' => json_encode($this->transportStats, \JSON_THROW_ON_ERROR),
        ]);
    }

    private function insertWorkerSample(): void
    {
        $transports = [];
        foreach ($this->transportStats as $transport => $stats) {
            $transports[$transport] = ['handled' => $stats['handled'], 'ms_sum' => (int) round($stats['ms_sum'])];
        }
        $this->connection->executeStatement(
            'INSERT INTO perf_sample (sampled_at, kind, subject, metrics) VALUES (NOW(), :kind, :subject, :metrics)',
            [
                'kind' => PerfSample::KIND_WORKER,
                'subject' => $this->workerKey,
                'metrics' => json_encode([
                    'name' => $this->workerName,
                    'busy_ms' => (int) round($this->busyMsTotal),
                    'handled' => $this->handledTotal,
                    'failed' => $this->failedTotal,
                    'memory' => memory_get_usage(true),
                    'transports' => $transports,
                ], \JSON_THROW_ON_ERROR),
            ],
        );
    }

    /**
     * Échantillonne la profondeur des files : le consumer cron ne passant que
     * toutes les 15 min, ce sont les workers (toujours vivants, même idle) qui
     * portent le pas de 1 min.
     */
    private function maybeInsertQueueSamples(): void
    {
        QueueSampler::sampleIfStale($this->connection);
    }

    private function nowS(): float
    {
        return (float) $this->clock->now()->format('U.u');
    }
}
