<?php

declare(strict_types=1);

namespace App\Pim\Completeness;

use App\Pim\Entity\CompletenessConfigurationAudit;
use App\Pim\Entity\CompletenessConfigurationRevision;
use App\Pim\Entity\CompletenessFieldConfiguration;
use App\Pim\Enum\TypeFiche;
use App\Pim\Message\RecalculateCompletenessBatch;
use App\Pim\Repository\CompletenessFieldConfigurationRepository;
use App\Shared\Outbox\OutboxPublisherInterface;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final readonly class CompletenessConfigurationSynchronizer
{
    public function __construct(
        private CompletenessFieldCatalog $catalog,
        private CompletenessFieldConfigurationRepository $repository,
        private EntityManagerInterface $entityManager,
        private OutboxPublisherInterface $outbox,
    ) {
    }

    public function synchronize(TypeFiche $type, string $actor = 'system'): CompletenessSynchronizationResult
    {
        return $this->entityManager->wrapInTransaction(function () use ($type, $actor): CompletenessSynchronizationResult {
            $revision = $this->entityManager->find(CompletenessConfigurationRevision::class, $type, LockMode::PESSIMISTIC_WRITE);
            if (!$revision instanceof CompletenessConfigurationRevision) {
                $revision = new CompletenessConfigurationRevision($type);
                $this->entityManager->persist($revision);
                $this->entityManager->flush();
            }
            $existing = [];
            foreach ($this->repository->findBy(['ficheType' => $type]) as $configuration) {
                $existing[$configuration->fieldCode()] = $configuration;
            }
            $changes = [];
            $catalogCodes = [];
            $created = 0;
            $deactivated = 0;
            foreach ($this->catalog->definitions($type) as $definition) {
                $catalogCodes[$definition->code] = true;
                $configuration = $existing[$definition->code] ?? null;
                if (!$configuration instanceof CompletenessFieldConfiguration) {
                    $configuration = new CompletenessFieldConfiguration($type, $definition->code, $definition->label);
                    $this->entityManager->persist($configuration);
                    $existing[$definition->code] = $configuration;
                    $changes[] = [$configuration, null, $configuration->snapshot()];
                    ++$created;
                } else {
                    $configuration->refreshLabel($definition->label);
                }
            }
            foreach ($existing as $code => $configuration) {
                if (!isset($catalogCodes[$code]) && $configuration->active()) {
                    $before = $configuration->snapshot();
                    $configuration->deactivate();
                    $changes[] = [$configuration, $before, $configuration->snapshot()];
                    ++$deactivated;
                }
            }
            $scheduled = [] !== $changes;
            if ($scheduled) {
                $revision->increment();
                $this->outbox->enqueue(new RecalculateCompletenessBatch($type->value, $revision->revision()));
                foreach ($changes as [$configuration, $before, $after]) {
                    $this->entityManager->persist(new CompletenessConfigurationAudit(
                        $type,
                        $configuration->fieldCode(),
                        $revision->revision(),
                        $actor,
                        'catalog_sync',
                        $before,
                        $after,
                    ));
                }
            }
            $this->entityManager->flush();

            return new CompletenessSynchronizationResult($type, $created, $deactivated, $revision->revision(), $scheduled);
        });
    }

    /** @return list<CompletenessSynchronizationResult> */
    public function synchronizeAll(string $actor = 'system'): array
    {
        $results = [];
        foreach ($this->catalog->supportedTypes() as $type) {
            $results[] = $this->synchronize($type, $actor);
        }

        return $results;
    }

    public function isSynchronized(TypeFiche $type): bool
    {
        $expected = array_fill_keys(array_map(static fn (CompletenessFieldDefinition $definition): string => $definition->code, $this->catalog->definitions($type)), true);
        $actual = [];
        foreach ($this->repository->findBy(['ficheType' => $type]) as $configuration) {
            $actual[$configuration->fieldCode()] = $configuration;
        }
        foreach (array_keys($expected) as $code) {
            if (!isset($actual[$code])) {
                return false;
            }
        }
        foreach ($actual as $code => $configuration) {
            if (!isset($expected[$code]) && $configuration->active()) {
                return false;
            }
        }

        return true;
    }
}
