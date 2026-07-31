<?php

declare(strict_types=1);

namespace App\Pim\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Pim\Api\Dto\LieuResource;
use App\Pim\Api\LieuApiMapper;

/** @implements ProviderInterface<LieuResource> */
final readonly class LieuItemProvider implements ProviderInterface
{
    public function __construct(private LieuApiState $state, private LieuApiMapper $mapper)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): LieuResource
    {
        return $this->mapper->lieu($this->state->lieu((string) ($uriVariables['id'] ?? '')));
    }
}
