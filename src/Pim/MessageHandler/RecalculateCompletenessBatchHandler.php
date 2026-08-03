<?php

declare(strict_types=1);

namespace App\Pim\MessageHandler;

use App\Pim\Completeness\CompletenessManager;
use App\Pim\Entity\CompletenessConfigurationRevision;
use App\Pim\Entity\Fiche;
use App\Pim\Enum\TypeFiche;
use App\Pim\Message\RecalculateCompletenessBatch;
use App\Pim\Repository\FicheRepository;
use App\Shared\Outbox\OutboxPublisherInterface;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Ulid;
use Psr\Log\LoggerInterface;

#[AsMessageHandler]
final readonly class RecalculateCompletenessBatchHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private FicheRepository $fiches,
        private CompletenessManager $manager,
        private OutboxPublisherInterface $outbox,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(RecalculateCompletenessBatch $message): void
    {
        $startedAt = microtime(true);
        $type = TypeFiche::from($message->ficheType);
        $revision = $this->entityManager->find(CompletenessConfigurationRevision::class, $type);
        if (!$revision instanceof CompletenessConfigurationRevision || $revision->revision() !== $message->revision) {
            return;
        }
        $limit = max(1, min(1000, $message->batchSize));
        $sql = 'SELECT id FROM pim_fiche WHERE type = :type';
        $params = ['type' => $type->value];
        $types = ['type' => ParameterType::STRING];
        if (null !== $message->afterId) {
            $sql .= ' AND id > :after_id';
            $params['after_id'] = Ulid::fromString($message->afterId)->toBinary();
            $types['after_id'] = ParameterType::BINARY;
        }
        $sql .= ' ORDER BY id ASC LIMIT '.$limit;
        $rows = $this->entityManager->getConnection()->executeQuery($sql, $params, $types)->fetchFirstColumn();
        $ids = [];
        $lastId = null;
        foreach ($rows as $binaryId) {
            $id = Ulid::fromBinary($binaryId);
            $lastId = (string) $id;
            $ids[] = $id;
        }
        /** @var list<Fiche> $fiches */
        $fiches = [] === $ids ? [] : $this->fiches->findBy(['id' => $ids]);
        $processed = $this->manager->calculateBatch($fiches, false);
        if (count($rows) === $limit) {
            $this->outbox->enqueue(new RecalculateCompletenessBatch($type->value, $message->revision, $lastId, $limit));
        }
        $this->entityManager->flush();
        $this->logger->info('Lot de complétude calculé.', [
            'fiche_type' => $type->value,
            'revision' => $message->revision,
            'after_id' => $message->afterId,
            'last_id' => $lastId,
            'selected' => count($rows),
            'loaded' => count($fiches),
            'processed' => $processed,
            'has_next' => count($rows) === $limit,
            'duration_ms' => (int) round(1000 * (microtime(true) - $startedAt)),
        ]);
    }
}
