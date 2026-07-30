<?php

namespace App\Pim\Service\Localisation;

use App\Pim\Model\ProviderPortal\DTO\Localisation\ComputeRouteDTO;
use App\Pim\Model\ProviderPortal\DTO\Localisation\CoordinatesDTO;
use App\Pim\Service\Client\BatchClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @extends BatchClientInterface<ComputeRouteDTO>
 */
interface ComputeRouteClientInterface extends BatchClientInterface
{
    /**
     * @param array<string, mixed> $options
     *
     * @return ($async is true ? ResponseInterface : ComputeRouteDTO[])
     */
    public function routes(CoordinatesDTO $origin, CoordinatesDTO $destination, array $options = [], bool $async = false): array|ResponseInterface;
}
