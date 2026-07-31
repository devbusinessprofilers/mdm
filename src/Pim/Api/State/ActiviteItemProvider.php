<?php

declare(strict_types=1);

namespace App\Pim\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Pim\Api\ActiviteApiMapper;
use App\Pim\Api\Dto\ActiviteResource;

/** @implements ProviderInterface<ActiviteResource> */
final readonly class ActiviteItemProvider implements ProviderInterface
{
    public function __construct(
        private ActiviteApiState $state,
        private ActiviteApiMapper $mapper,
    ) {
    }

    public function provide(
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): ActiviteResource {
        return $this->mapper->activite(
            $this->state->activite((string) ($uriVariables['id'] ?? '')),
        );
    }
}
