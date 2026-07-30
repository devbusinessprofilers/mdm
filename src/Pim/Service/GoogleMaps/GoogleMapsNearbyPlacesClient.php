<?php

namespace App\Pim\Service\GoogleMaps;

use App\Pim\Model\ProviderPortal\DTO\Localisation\CoordinatesDTO;
use App\Pim\Model\ProviderPortal\DTO\Localisation\NearbyPlaceDTO;
use App\Pim\Service\Localisation\NearbyPlaceClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class GoogleMapsNearbyPlacesClient implements GoogleMapsPlacesClientInterface, NearbyPlaceClientInterface
{
    private const DISTANCE_FAR = 50000.0;
    private const DISTANCE_MEDIUM = 1200.0;
    private const DISTANCE_CLOSE = 500.0;

    private const RANK_PREFERENCE_DISTANCE = 'DISTANCE';
    private const RANK_PREFERENCE_POPULARITY = 'POPULARITY';

    private const MAX_RESULT_COUNT_DEFAULT = 10;

    public function __construct(
        private readonly HttpClientInterface $googleMapPlacesClient,
        private readonly LocaleSwitcher $localeSwitcher,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function nearbyTrainStations(CoordinatesDTO $position, bool $async = false): array|ResponseInterface
    {
        return $this->nearbySearch($position, options: [
            'includedPrimaryTypes' => [self::PLACE_TYPE_TRAIN_STATION],
        ], async: $async);
    }

    public function nearbySubwayStations(CoordinatesDTO $position, bool $async = false): array|ResponseInterface
    {
        return $this->nearbySearch($position, self::DISTANCE_CLOSE, [
            'includedPrimaryTypes' => [self::PLACE_TYPE_SUBWAY_STATION],
        ], $async);
    }

    public function nearbyLightRailStations(CoordinatesDTO $position, bool $async = false): array|ResponseInterface
    {
        return $this->nearbySearch($position, self::DISTANCE_CLOSE, [
            'includedPrimaryTypes' => [self::PLACE_TYPE_LIGHT_RAIL_STATION],
        ], $async);
    }

    public function nearbyAirports(CoordinatesDTO $position, bool $async = false): array|ResponseInterface
    {
        return $this->nearbySearch($position, options: [
            'includedPrimaryTypes' => [self::PLACE_TYPE_AIRPORT],
        ], async: $async);
    }

    public function nearbyCities(CoordinatesDTO $position, bool $async = false): array|ResponseInterface
    {
        return $this->nearbySearch($position, options: [
            'includedPrimaryTypes' => [self::PLACE_TYPE_CITY],
        ], async: $async);
    }

    public function nearbyParkings(CoordinatesDTO $position, bool $async = false): array|ResponseInterface
    {
        return $this->nearbySearch($position, self::DISTANCE_CLOSE, [
            'includedPrimaryTypes' => [self::PLACE_TYPE_PARKING],
        ], $async);
    }

    public function nearbyPointsOfInterest(CoordinatesDTO $position): array
    {
        $places = $this->nearbySearch($position, self::DISTANCE_MEDIUM, [
            'rankPreference' => self::RANK_PREFERENCE_POPULARITY,
            'maxResultCount' => 20,
        ]);

        $places = \array_filter($places, fn (NearbyPlaceDTO $place) => \in_array(self::PLACE_TYPE_POINT_OF_INTEREST, $place->types ?? [], true));
        \usort($places, fn (NearbyPlaceDTO $a, NearbyPlaceDTO $b) => ($b->rating ?? 0) <=> ($a->rating ?? 0));

        return \array_slice($places, 0, 5);
    }

    public function resolve(array $responses): array
    {
        $results = [];
        foreach ($responses as $identifier => $response) {
            $results[$identifier] = $this->formatResponse($response);
        }

        return $results;
    }

    private function nearbySearch(CoordinatesDTO $position, float $radius = self::DISTANCE_FAR, array $options = [], bool $async = false): array|ResponseInterface
    {
        $response = $this->googleMapPlacesClient->request(
            'POST',
            '/v1/places:searchNearby',
            [
                'headers' => $this->buildHeaders([
                    'X-Goog-FieldMask' => '*',
                    ...($options['headers'] ?? []),
                ]),
                'json' => [
                    'locationRestriction' => [
                        'circle' => [
                            'center' => [
                                'latitude' => $position->latitude,
                                'longitude' => $position->longitude,
                            ],
                            'radius' => $radius,
                        ],
                    ],
                    'rankPreference' => $options['rankPreference'] ?? self::RANK_PREFERENCE_DISTANCE,
                    'maxResultCount' => $options['maxResultCount'] ?? self::MAX_RESULT_COUNT_DEFAULT,
                    ...$options,
                ],
            ]
        );

        return $async ? $response : $this->formatResponse($response);
    }

    private function buildHeaders(array $options = []): array
    {
        return [
            'Accept-Language' => $this->localeSwitcher->getLocale(),
            ...$options,
        ];
    }

    /**
     * @return NearbyPlaceDTO[]
     */
    private function formatResponse(ResponseInterface $response): array
    {
        if (200 !== $response->getStatusCode()) {
            $this->logger->error('Google Maps nearby place error', [
                'status_code' => $response->getStatusCode(),
            ]);

            return [];
        }

        $data = $response->toArray();

        $nearbyPlaces = [];
        $places = $data['places'] ?? [];
        foreach ($places as $place) {
            $id = $place['id'] ?? null;
            if (!$id) {
                continue;
            }

            $displayName = $place['displayName'] ?? null;
            if ($displayName && \is_array($displayName)) {
                $displayName = $displayName['text'] ?? null;
            }

            $nearbyPlace = (new NearbyPlaceDTO($id))
                ->setDisplayName($displayName)
                ->setUri($place['googleMapsUri'] ?? null)
                ->setTypes($place['types'] ?? null)
                ->setRating($place['rating'] ?? null);

            $location = $place['location'] ?? null;
            if ($location && \is_array($location)) {
                $nearbyPlace->setPosition(new CoordinatesDTO($location['latitude'] ?? null, $location['longitude'] ?? null));
            }

            $nearbyPlaces[] = $nearbyPlace;
        }

        return $nearbyPlaces;
    }
}
