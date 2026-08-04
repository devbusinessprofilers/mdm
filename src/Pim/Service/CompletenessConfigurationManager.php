<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Completeness\CompletenessRecalculationScheduler;
use App\Pim\Entity\CompletenessConfigurationAudit;
use App\Pim\Entity\CompletenessFieldConfiguration;
use App\Pim\Enum\CompletenessFormula;
use Doctrine\ORM\EntityManagerInterface;

final readonly class CompletenessConfigurationManager
{
    public function __construct(
        private CompletenessRecalculationScheduler $scheduler,
        private EntityManagerInterface $entityManager,
    ) {}

    /** @param array<string, mixed> $data */
    public function update(CompletenessFieldConfiguration $configuration, array $data, string $actor): bool
    {
        $before = $configuration->snapshot();
        $configuration->configure(
            $data['formula'] instanceof CompletenessFormula ? $data['formula'] : CompletenessFormula::Presence,
            (float) $data['weight'],
            null === $data['targetLengthOverride'] ? null : (int) $data['targetLengthOverride'],
            (bool) $data['active'],
            (bool) $data['marketplace'],
            (bool) $data['thematicSites'],
            (bool) $data['salesforce'],
            (bool) $data['providerPortal'],
        );
        if ($before === $configuration->snapshot()) {
            return false;
        }
        $revision = $this->scheduler->schedule($configuration->ficheType());
        $this->entityManager->persist(new CompletenessConfigurationAudit(
            $configuration->ficheType(),
            $configuration->fieldCode(),
            $revision,
            $actor,
            'admin',
            $before,
            $configuration->snapshot(),
        ));
        $this->entityManager->flush();

        return true;
    }
}
