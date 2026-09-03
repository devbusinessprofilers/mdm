<?php

declare(strict_types=1);

namespace App\Pim\MessageHandler;

use App\Pim\Completeness\CompletenessManager;
use App\Pim\Enum\TypeFiche;
use App\Pim\Message\RecalculateCompletenessBatch;
use App\Pim\Repository\CompletenessConfigurationRevisionRepository;
use App\Pim\Repository\FicheRepository;
use App\Shared\Outbox\OutboxPublisherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RecalculateCompletenessBatchHandler
{
    public function __construct(
        private FicheRepository $fiches,
        private CompletenessConfigurationRevisionRepository $revisions,
        private CompletenessManager $manager,
        private OutboxPublisherInterface $outbox,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(RecalculateCompletenessBatch $message): void
    {
        $startedAt = microtime(true);
        $type = TypeFiche::from($message->ficheType);
        $revision = $this->revisions->findForType($type);
        if (null === $revision || $revision->revision() !== $message->revision) {
            return;
        }
        $limit = max(1, min(1000, $message->batchSize));
        $fiches = $this->fiches->findBatchAfter($type, $message->afterId, $limit);
        $last = [] === $fiches ? null : $fiches[array_key_last($fiches)];
        $lastId = $last?->idString();
        $processed = $this->manager->calculateBatch($fiches, false);
        if (count($fiches) === $limit) {
            $this->outbox->enqueue(new RecalculateCompletenessBatch($type->value, $message->revision, $lastId, $limit));
        }
        $this->logger->info('Lot de complétude calculé.', [
            'fiche_type' => $type->value,
            'revision' => $message->revision,
            'after_id' => $message->afterId,
            'last_id' => $lastId,
            'selected' => count($fiches),
            'loaded' => count($fiches),
            'processed' => $processed,
            'has_next' => count($fiches) === $limit,
            'duration_ms' => (int) round(1000 * (microtime(true) - $startedAt)),
        ]);
    }
}
