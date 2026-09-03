<?php

declare(strict_types=1);

namespace App\Dashboard\MessageHandler;

use App\Dashboard\Entity\DashboardSnapshot;
use App\Dashboard\Message\ComputeDashboardStats;
use App\Dashboard\Repository\DashboardSnapshotRepository;
use App\Dashboard\Service\DashboardStatsCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ComputeDashboardStatsHandler
{
    /** Au-delà de cette fenêtre, l'historique est compacté à un snapshot par jour. */
    private const string COMPACTION_WINDOW = '-7 days';

    public function __construct(
        private DashboardStatsCalculator $calculator,
        private DashboardSnapshotRepository $snapshots,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ComputeDashboardStats $message): void
    {
        $startedAt = microtime(true);
        $payload = $this->calculator->compute();
        $durationMs = (int) round(1000 * (microtime(true) - $startedAt));
        $snapshot = new DashboardSnapshot($payload, $durationMs);
        $this->entityManager->persist($snapshot);
        $compacted = $this->snapshots->compactOlderThan(
            new \DateTimeImmutable(self::COMPACTION_WINDOW),
        );
        $this->logger->info('Statistiques du tableau de bord calculées.', [
            'snapshot_id' => $snapshot->id(),
            'duration_ms' => $durationMs,
            'compacted' => $compacted,
        ]);
    }
}
