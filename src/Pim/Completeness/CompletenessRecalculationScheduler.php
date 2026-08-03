<?php

declare(strict_types=1);

namespace App\Pim\Completeness;

use App\Pim\Entity\CompletenessConfigurationRevision;
use App\Pim\Enum\TypeFiche;
use App\Pim\Message\RecalculateCompletenessBatch;
use App\Shared\Outbox\OutboxPublisherInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class CompletenessRecalculationScheduler
{
    public function __construct(private EntityManagerInterface $entityManager, private OutboxPublisherInterface $outbox)
    {
    }

    public function schedule(TypeFiche $type, bool $incrementRevision = true, int $batchSize = 250): int
    {
        $revision = $this->entityManager->find(CompletenessConfigurationRevision::class, $type);
        if (!$revision instanceof CompletenessConfigurationRevision) {
            $revision = new CompletenessConfigurationRevision($type);
            $this->entityManager->persist($revision);
        } elseif ($incrementRevision) {
            $revision->increment();
        }
        $this->outbox->enqueue(new RecalculateCompletenessBatch($type->value, $revision->revision(), null, max(1, min(1000, $batchSize))));

        return $revision->revision();
    }
}
