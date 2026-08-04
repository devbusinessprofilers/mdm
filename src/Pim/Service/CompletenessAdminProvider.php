<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Completeness\CompletenessFieldCatalog;
use App\Pim\Entity\CompletenessFieldConfiguration;
use App\Pim\Enum\TypeFiche;
use App\Pim\Repository\CompletenessConfigurationRevisionRepository;
use App\Pim\Repository\CompletenessFieldConfigurationRepository;
use App\Pim\Repository\CompletenessRepository;

final readonly class CompletenessAdminProvider
{
    public function __construct(
        private CompletenessFieldCatalog $catalog,
        private CompletenessFieldConfigurationRepository $configurations,
        private CompletenessConfigurationRevisionRepository $revisions,
        private CompletenessRepository $completeness,
    ) {}

    /** @return list<array{configuration: CompletenessFieldConfiguration, definition: \App\Pim\Completeness\CompletenessFieldDefinition, effective_target: int|null}> */
    public function rows(?TypeFiche $selectedType, string $query): array
    {
        $configurations = null === $selectedType
            ? $this->configurations->findBy([], ['ficheType' => 'ASC', 'fieldCode' => 'ASC'])
            : $this->configurations->findBy(['ficheType' => $selectedType], ['fieldCode' => 'ASC']);
        $query = mb_strtolower(trim($query));
        $rows = [];
        foreach ($configurations as $configuration) {
            $definition = $this->catalog->find($configuration->ficheType(), $configuration->fieldCode());
            if (null === $definition) {
                continue;
            }
            if ('' !== $query && !str_contains(mb_strtolower($configuration->fieldCode().' '.$configuration->label()), $query)) {
                continue;
            }
            $rows[] = [
                'configuration' => $configuration,
                'definition' => $definition,
                'effective_target' => $configuration->targetLengthOverride() ?? $definition->defaultTargetLength,
            ];
        }

        return $rows;
    }

    /** @return array<string, array{revision: int, pending: int}> */
    public function status(): array
    {
        $status = [];
        foreach ($this->catalog->supportedTypes() as $type) {
            $revision = $this->revisions->findForType($type)?->revision() ?? 1;
            $status[$type->value] = [
                'revision' => $revision,
                'pending' => $this->completeness->countPending($type, $revision),
            ];
        }

        return $status;
    }
}
