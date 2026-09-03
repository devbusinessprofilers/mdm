<?php

declare(strict_types=1);

namespace App\Dashboard\MessageHandler;

use App\Dashboard\Entity\DashboardSnapshot;
use App\Dashboard\Message\ComputeFieldFillRates;
use App\Dashboard\Service\FieldFillRateCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ComputeFieldFillRatesHandler
{
    public function __construct(
        private FieldFillRateCalculator $calculator,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ComputeFieldFillRates $message): void
    {
        $startedAt = microtime(true);
        $payload = $this->calculator->compute();
        $durationMs = (int) round(1000 * (microtime(true) - $startedAt));
        // Le calculateur vide l'EntityManager entre les lots : persister après.
        $snapshot = new DashboardSnapshot($payload, $durationMs, DashboardSnapshot::KIND_FIELD_FILL);
        $this->entityManager->persist($snapshot);
        $this->logger->info('Taux de remplissage des champs calculés.', [
            'snapshot_id' => $snapshot->id(),
            'duration_ms' => $durationMs,
        ]);
    }
}
