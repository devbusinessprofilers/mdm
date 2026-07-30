<?php

namespace App\Pim\Service\GoogleMaps;

use App\Pim\Model\ProviderPortal\DTO\Localisation\ComputeRouteDTO;
use App\Pim\Model\ProviderPortal\DTO\Localisation\CoordinatesDTO;
use App\Pim\Service\Localisation\ComputeRouteClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class GoogleMapsComputeRoutesClient implements ComputeRouteClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $googleMapRoutesClient,
        private readonly LocaleSwitcher $localeSwitcher,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function routes(CoordinatesDTO $origin, CoordinatesDTO $destination, array $options = [], bool $async = false): array|ResponseInterface
    {
        $response = $this->googleMapRoutesClient->request(
            'POST',
            '/directions/v2:computeRoutes',
            [
                'headers' => $this->buildHeaders([
                    'X-Goog-FieldMask' => 'routes.duration,routes.distanceMeters',
                    ...($options['headers'] ?? []),
                ]),
                'json' => [
                    'origin' => [
                        'location' => [
                            'latLng' => [
                                'latitude' => $origin->latitude,
                                'longitude' => $origin->longitude,
                            ],
                        ],
                    ],
                    'destination' => [
                        'location' => [
                            'latLng' => [
                                'latitude' => $destination->latitude,
                                'longitude' => $destination->longitude,
                            ],
                        ],
                    ],
                    'units' => 'METRIC',
                    'travelMode' => 'DRIVE',
                    'routingPreference' => 'TRAFFIC_AWARE',
                    'computeAlternativeRoutes' => false,
                    'routeModifiers' => [
                        'avoidTolls' => false,
                        'avoidHighways' => false,
                        'avoidFerries' => false,
                    ],
                    ...$options,
                ],
            ]
        );

        return $async ? $response : $this->formatResponse($response);
    }

    public function resolve(array $responses): array
    {
        $results = [];
        foreach ($responses as $identifier => $response) {
            $results[$identifier] = $this->formatResponse($response);
        }

        return $results;
    }

    private function buildHeaders(array $options = []): array
    {
        return [
            'Accept-Language' => $this->localeSwitcher->getLocale(),
            ...$options,
        ];
    }

    /**
     * @return ComputeRouteDTO[] $routes
     */
    private function formatResponse(ResponseInterface $response): array
    {
        if (200 !== $response->getStatusCode()) {
            $this->logger->error('Google Maps compute route error', [
                'status_code' => $response->getStatusCode(),
            ]);

            return [];
        }

        $data = $response->toArray();

        $computeRoutes = [];
        $routes = $data['routes'] ?? [];
        foreach ($routes as $route) {
            $duration = $route['duration'] ?? null;
            $distance = $route['distanceMeters'] ?? null;
            if (!$duration || !$distance) {
                continue;
            }

            $duration = str_replace('s', '', $duration);
            $duration = intval($duration, 10) / 60;

            $distance = $distance / 1000;

            $computeRoutes[] = (new ComputeRouteDTO())
                ->setDuration($duration)
                ->setDistance($distance);
        }

        return $computeRoutes;
    }
}
