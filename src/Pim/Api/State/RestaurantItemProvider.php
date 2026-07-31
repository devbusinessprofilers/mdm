<?php

declare(strict_types=1);

namespace App\Pim\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Pim\Api\Dto\RestaurantResource;
use App\Pim\Api\RestaurantApiMapper;

/** @implements ProviderInterface<RestaurantResource> */
final readonly class RestaurantItemProvider implements ProviderInterface
{
    public function __construct(
        private RestaurantApiState $state,
        private RestaurantApiMapper $mapper,
    ) {
    }

    public function provide(
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): RestaurantResource {
        return $this->mapper->restaurant(
            $this->state->restaurant((string) ($uriVariables['id'] ?? '')),
        );
    }
}
