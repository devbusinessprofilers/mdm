<?php

namespace App\Pim\Service\Localisation;

use App\Pim\Model\ProviderPortal\DTO\Localisation\CoordinatesDTO;
use App\Pim\Model\ProviderPortal\DTO\Localisation\NearbyPlaceDTO;
use App\Pim\Service\Client\BatchClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @extends BatchClientInterface<NearbyPlaceDTO>
 */
interface NearbyPlaceClientInterface extends BatchClientInterface
{
    /**
     * @return ($async is true ? ResponseInterface : NearbyPlaceDTO[])
     */
    public function nearbyTrainStations(CoordinatesDTO $position, bool $async = false): array|ResponseInterface;

    /**
     * @return ($async is true ? ResponseInterface : NearbyPlaceDTO[])
     */
    public function nearbySubwayStations(CoordinatesDTO $position, bool $async = false): array|ResponseInterface;

    /**
     * @return ($async is true ? ResponseInterface : NearbyPlaceDTO[])
     */
    public function nearbyLightRailStations(CoordinatesDTO $position, bool $async = false): array|ResponseInterface;

    /**
     * @return ($async is true ? ResponseInterface : NearbyPlaceDTO[])
     */
    public function nearbyAirports(CoordinatesDTO $position, bool $async = false): array|ResponseInterface;

    /**
     * @return ($async is true ? ResponseInterface : NearbyPlaceDTO[])
     */
    public function nearbyCities(CoordinatesDTO $position, bool $async = false): array|ResponseInterface;

    /**
     * @return ($async is true ? ResponseInterface : NearbyPlaceDTO[])
     */
    public function nearbyParkings(CoordinatesDTO $position, bool $async = false): array|ResponseInterface;

    /**
     * @return NearbyPlaceDTO[]
     */
    public function nearbyPointsOfInterest(CoordinatesDTO $position): array;
}
