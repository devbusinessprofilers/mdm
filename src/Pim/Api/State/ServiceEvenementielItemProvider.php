<?php

declare(strict_types=1);

namespace App\Pim\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Pim\Api\ServiceEvenementielApiMapper;
use App\Pim\Api\Dto\ServiceEvenementielResource;

/** @implements ProviderInterface<ServiceEvenementielResource> */
final readonly class ServiceEvenementielItemProvider implements
    ProviderInterface
{
    public function __construct(
        private ServiceEvenementielApiState $state,
        private ServiceEvenementielApiMapper $mapper,
    ) {}

    public function provide(
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): ServiceEvenementielResource {
        return $this->mapper->service(
            $this->state->service((string) ($uriVariables["id"] ?? "")),
        );
    }
}
