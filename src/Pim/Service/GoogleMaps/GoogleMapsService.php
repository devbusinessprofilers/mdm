<?php

namespace App\Pim\Service\GoogleMaps;


use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GoogleMapsService
{
    public const ORIGIN_TYPE_CITY = 'CITY';
    public const ORIGIN_TYPE_DEPARTMENT = 'DEPARTMENT';
    public const ORIGIN_TYPE_REGION = 'REGION';

    private const GEOCODE_ENDPOINT = 'https://maps.googleapis.com/maps/api/geocode/json';
    private const MATRIX_ENDPOINT = 'https://routes.googleapis.com/distanceMatrix/v2:computeRouteMatrix';
    private const ROUTES_ENDPOINT = 'https://routes.googleapis.com/directions/v2:computeRoutes';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly VilleRepository $villeRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly string $google_maps_api_key
    ) {
    }

    /**
     * Returns coordinates from a search localization label.
     *
     * @return array{lat: float, lng: float}|null
     */
    public function getLatLngByLocalization(string $localization): ?array
    {
        $origin = $this->resolveRouteOrigin($localization);
        if ($origin !== null) {
            return [
                'lat' => $origin['latitude'],
                'lng' => $origin['longitude'],
            ];
        }

        return $this->geocodeAddress($localization);
    }

    /**
     * Resolves route origin as cache key + coordinates.
     *
     * @return array{
     *     origin_type: string,
     *     origin_id: int,
     *     latitude: float,
     *     longitude: float
     * }|null
     */
    public function resolveRouteOrigin(string $localization): ?array
    {
        $localization = trim($localization);
        if ($localization === '') {
            return null;
        }

        $baseLocalization = $this->stripCountrySuffix($localization);

        $department = $this->extractDepartmentName($baseLocalization);
        if ($department !== null) {
            return $this->resolveDepartmentOrigin($department);
        }

        $region = $this->extractRegionName($baseLocalization);
        if ($region !== null) {
            return $this->resolveRegionOrigin($region);
        }

        return $this->resolveCityOrigin($baseLocalization);
    }

    /**
     * @param array<int, array{lieuId:int,lat:float,lng:float}> $destinations
     * @return array<int, array{distance_meters:int,duration_seconds:int}>
     */
    public function computeRouteMatrix(float $originLat, float $originLng, array $destinations): array
    {
        return $this->computeRouteMatrixDetailed($originLat, $originLng, $destinations)['metricsByLieuId'];
    }

    /**
     * @param array<int, array{lieuId:int,lat:float,lng:float}> $destinations
     * @return array{
     *     metricsByLieuId: array<int, array{distance_meters:int,duration_seconds:int}>,
     *     noRouteLieuIds: int[],
     *     requestSucceeded: bool
     * }
     */
    public function computeRouteMatrixDetailed(float $originLat, float $originLng, array $destinations): array
    {
        if ($destinations === []) {
            return [
                'metricsByLieuId' => [],
                'noRouteLieuIds' => [],
                'requestSucceeded' => true,
            ];
        }

        $requestBody = [
            'origins' => [[
                'waypoint' => [
                    'location' => [
                        'latLng' => [
                            'latitude' => $originLat,
                            'longitude' => $originLng,
                        ],
                    ],
                ],
            ]],
            'destinations' => array_map(static function (array $destination): array {
                return [
                    'waypoint' => [
                        'location' => [
                            'latLng' => [
                                'latitude' => $destination['lat'],
                                'longitude' => $destination['lng'],
                            ],
                        ],
                    ],
                ];
            }, $destinations),
            'travelMode' => 'DRIVE',
            'languageCode' => 'fr-FR',
            'units' => 'METRIC',
        ];

        try {
            $response = $this->httpClient->request('POST', self::MATRIX_ENDPOINT, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Goog-Api-Key' => $this->google_maps_api_key,
                    'X-Goog-FieldMask' => 'originIndex,destinationIndex,distanceMeters,duration,condition,status',
                ],
                'json' => $requestBody,
            ]);
            $content = $response->getContent();
        } catch (TransportExceptionInterface|\Throwable $exception) {
            $this->logger->error('Google Routes matrix call failed', [
                'exception' => $exception->getMessage(),
            ]);

            return [
                'metricsByLieuId' => [],
                'noRouteLieuIds' => [],
                'requestSucceeded' => false,
            ];
        }

        $rows = $this->decodeMatrixResponse($content);
        $metricsByLieuId = [];
        $noRouteLieuIds = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $destinationIndex = isset($row['destinationIndex']) ? (int) $row['destinationIndex'] : null;
            if ($destinationIndex === null || !isset($destinations[$destinationIndex])) {
                continue;
            }

            $lieuId = (int) $destinations[$destinationIndex]['lieuId'];
            $distanceMeters = isset($row['distanceMeters']) ? (int) $row['distanceMeters'] : null;
            $durationSeconds = $this->parseDurationToSeconds($row['duration'] ?? null);
            $condition = strtoupper((string) ($row['condition'] ?? ''));

            $hasValidRoute = $distanceMeters !== null
                && $distanceMeters >= 0
                && $durationSeconds !== null
                && ($condition === '' || $condition === 'ROUTE_EXISTS');

            if ($hasValidRoute) {
                $metricsByLieuId[$lieuId] = [
                    'distance_meters' => $distanceMeters,
                    'duration_seconds' => $durationSeconds,
                ];

                continue;
            }

            // Negative-cache only explicit route misses, not ambiguous malformed rows.
            if ($condition !== '' && $condition !== 'ROUTE_EXISTS') {
                $noRouteLieuIds[$lieuId] = $lieuId;
            }
        }

        foreach (array_keys($metricsByLieuId) as $lieuId) {
            unset($noRouteLieuIds[$lieuId]);
        }

        return [
            'metricsByLieuId' => $metricsByLieuId,
            'noRouteLieuIds' => array_values($noRouteLieuIds),
            'requestSucceeded' => true,
        ];
    }

    /**
     * @return array{
     *     distance_meters:int,
     *     duration_seconds:int,
     *     distance_text:string,
     *     duration_text:string,
     *     polyline:?string,
     *     steps:array<int, array{
     *         instructions:string,
     *         distance_meters:int,
     *         duration_seconds:int,
     *         distance_text:string,
     *         duration_text:string,
     *         travel_mode:string,
     *         transit:array{
     *             line_name:?string,
     *             line_color:?string,
     *             text_color:?string,
     *             vehicle_type:?string
     *         }|null
     *     }>
     * }|null
     */
    public function computeRoutes(string $originAddress, float $destinationLat, float $destinationLng, string $travelMode): ?array
    {
        $originAddress = trim($originAddress);
        if ($originAddress === '') {
            return null;
        }

        $requestBody = [
            'origin' => [
                'address' => $originAddress,
            ],
            'destination' => [
                'location' => [
                    'latLng' => [
                        'latitude' => $destinationLat,
                        'longitude' => $destinationLng,
                    ],
                ],
            ],
            'travelMode' => $this->mapTravelMode($travelMode),
            'languageCode' => 'fr-FR',
            'units' => 'METRIC',
            'computeAlternativeRoutes' => false,
            'polylineQuality' => 'OVERVIEW',
            'polylineEncoding' => 'ENCODED_POLYLINE',
        ];

        try {
            $response = $this->httpClient->request('POST', self::ROUTES_ENDPOINT, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Goog-Api-Key' => $this->google_maps_api_key,
                    'X-Goog-FieldMask' => implode(',', [
                        'routes.distanceMeters',
                        'routes.duration',
                        'routes.polyline.encodedPolyline',
                        'routes.legs.steps.navigationInstruction.instructions',
                        'routes.legs.steps.distanceMeters',
                        'routes.legs.steps.staticDuration',
                        'routes.legs.steps.travelMode',
                        'routes.legs.steps.transitDetails',
                    ]),
                ],
                'json' => $requestBody,
            ]);
            $payload = $response->toArray();
        } catch (TransportExceptionInterface|\Throwable $exception) {
            $this->logger->error('Google Routes computeRoutes call failed', [
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }

        $route = $payload['routes'][0] ?? null;
        if (!is_array($route)) {
            return null;
        }

        $distanceMeters = isset($route['distanceMeters']) ? (int) $route['distanceMeters'] : null;
        $durationSeconds = $this->parseDurationToSeconds($route['duration'] ?? null);
        if ($distanceMeters === null || $durationSeconds === null) {
            return null;
        }

        $steps = [];
        foreach (($route['legs'] ?? []) as $leg) {
            if (!is_array($leg)) {
                continue;
            }

            foreach (($leg['steps'] ?? []) as $step) {
                if (!is_array($step)) {
                    continue;
                }

                $stepDistance = isset($step['distanceMeters']) ? (int) $step['distanceMeters'] : 0;
                $stepDuration = $this->parseDurationToSeconds($step['staticDuration'] ?? null) ?? 0;
                $transitDetails = $step['transitDetails'] ?? null;

                $steps[] = [
                    'instructions' => (string) ($step['navigationInstruction']['instructions'] ?? ''),
                    'distance_meters' => $stepDistance,
                    'duration_seconds' => $stepDuration,
                    'distance_text' => $this->formatDistance($stepDistance),
                    'duration_text' => $this->formatDuration($stepDuration),
                    'travel_mode' => strtoupper((string) ($step['travelMode'] ?? 'DRIVE')),
                    'transit' => is_array($transitDetails) ? [
                        'line_name' => $transitDetails['transitLine']['nameShort'] ?? $transitDetails['transitLine']['name'] ?? null,
                        'line_color' => $transitDetails['transitLine']['color'] ?? null,
                        'text_color' => $transitDetails['transitLine']['textColor'] ?? null,
                        'vehicle_type' => $transitDetails['transitLine']['vehicle']['type'] ?? null,
                    ] : null,
                ];
            }
        }

        return [
            'distance_meters' => $distanceMeters,
            'duration_seconds' => $durationSeconds,
            'distance_text' => $this->formatDistance($distanceMeters),
            'duration_text' => $this->formatDuration($durationSeconds),
            'polyline' => $route['polyline']['encodedPolyline'] ?? null,
            'steps' => $steps,
        ];
    }

    private function resolveCityOrigin(string $localization): ?array
    {
        [$cityName, $zipCode] = $this->extractCityAndZip($localization);
        if ($cityName === null) {
            return null;
        }

        $ville = null;
        if ($zipCode !== null) {
            $ville = $this->villeRepository->findOneBy(['nom' => $cityName, 'codePostal' => $zipCode]);
        }

        if (!$ville instanceof Ville) {
            $ville = $this->villeRepository->findOneBy(['nom' => $cityName]);
        }

        if (!$ville instanceof Ville || $ville->getId() === null) {
            return null;
        }

        $location = $this->getOrRefreshCityCoordinates($ville, $localization);
        if ($location === null) {
            return null;
        }

        return [
            'origin_type' => self::ORIGIN_TYPE_CITY,
            'origin_id' => (int) $ville->getId(),
            'latitude' => $location['lat'],
            'longitude' => $location['lng'],
        ];
    }

    private function resolveDepartmentOrigin(string $departmentName): ?array
    {
        /** @var Departement|null $department */
        $department = $this->entityManager->getRepository(Departement::class)->findOneBy(['nom' => $departmentName]);
        if (!$department instanceof Departement || $department->getId() === null) {
            return null;
        }

        $location = $this->findDepartmentCentroid((int) $department->getId()) ?? $this->geocodeAddress($departmentName . ', France');
        if ($location === null) {
            return null;
        }

        return [
            'origin_type' => self::ORIGIN_TYPE_DEPARTMENT,
            'origin_id' => (int) $department->getId(),
            'latitude' => $location['lat'],
            'longitude' => $location['lng'],
        ];
    }

    private function resolveRegionOrigin(string $regionName): ?array
    {
        /** @var Region|null $region */
        $region = $this->entityManager->getRepository(Region::class)->findOneBy(['nom' => $regionName]);
        if (!$region instanceof Region || $region->getId() === null) {
            return null;
        }

        $location = $this->findRegionCentroid((int) $region->getId()) ?? $this->geocodeAddress($regionName . ', France');
        if ($location === null) {
            return null;
        }

        return [
            'origin_type' => self::ORIGIN_TYPE_REGION,
            'origin_id' => (int) $region->getId(),
            'latitude' => $location['lat'],
            'longitude' => $location['lng'],
        ];
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
    private function getOrRefreshCityCoordinates(Ville $ville, string $fallbackAddress): ?array
    {
        $lat = $this->toFloat($ville->getLat());
        $lng = $this->toFloat($ville->getLng());
        if ($lat !== null && $lng !== null) {
            return [
                'lat' => $lat,
                'lng' => $lng,
            ];
        }

        $address = $ville->getNom();
        if ($ville->getCodePostal() !== null) {
            $address .= ' (' . $ville->getCodePostal() . ')';
        }

        $location = $this->geocodeAddress($address) ?? $this->geocodeAddress($fallbackAddress);
        if ($location === null) {
            return null;
        }

        $ville
            ->setLat((string) $location['lat'])
            ->setLng((string) $location['lng'])
            ->setPlaceId($location['place_id'] ?? null)
            ->setFormattedAddress($location['formatted_address'] ?? null);

        $this->entityManager->persist($ville);
        $this->entityManager->flush();

        return $location;
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
    private function findDepartmentCentroid(int $departmentId): ?array
    {
        $publishedLieuCondition = $this->buildPublishedLieuCondition('p');
        $sql = <<<SQL
            SELECT AVG(p.latitude) AS lat, AVG(p.longitude) AS lng
            FROM bp_produit p
            INNER JOIN bp_ville v ON v.id = p.ville_id
            WHERE v.departement_id = :departmentId
              AND {$publishedLieuCondition}
              AND p.latitude IS NOT NULL
              AND p.longitude IS NOT NULL
        SQL;

        $row = $this->entityManager->getConnection()
            ->executeQuery($sql, ['departmentId' => $departmentId])
            ->fetchAssociative();

        $lat = $this->toFloat($row['lat'] ?? null);
        $lng = $this->toFloat($row['lng'] ?? null);
        if ($lat === null || $lng === null) {
            return null;
        }

        return [
            'lat' => $lat,
            'lng' => $lng,
        ];
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
    private function findRegionCentroid(int $regionId): ?array
    {
        $publishedLieuCondition = $this->buildPublishedLieuCondition('p');
        $sql = <<<SQL
            SELECT AVG(p.latitude) AS lat, AVG(p.longitude) AS lng
            FROM bp_produit p
            INNER JOIN bp_ville v ON v.id = p.ville_id
            INNER JOIN bp_departement d ON d.id = v.departement_id
            WHERE d.region_id = :regionId
              AND {$publishedLieuCondition}
              AND p.latitude IS NOT NULL
              AND p.longitude IS NOT NULL
        SQL;

        $row = $this->entityManager->getConnection()
            ->executeQuery($sql, ['regionId' => $regionId])
            ->fetchAssociative();

        $lat = $this->toFloat($row['lat'] ?? null);
        $lng = $this->toFloat($row['lng'] ?? null);
        if ($lat === null || $lng === null) {
            return null;
        }

        return [
            'lat' => $lat,
            'lng' => $lng,
        ];
    }

    private function buildPublishedLieuCondition(string $productAlias): string
    {
        return <<<SQL
            EXISTS (
                SELECT 1
                FROM bp_lieu l
                WHERE l.produit_id = {$productAlias}.id
                  AND l.is_published = 1
            )
        SQL;
    }

    /**
     * @return array{lat: float, lng: float, place_id?: ?string, formatted_address?: ?string}|null
     */
    private function geocodeAddress(string $address): ?array
    {
        $address = trim($address);
        if ($address === '') {
            return null;
        }

        try {
            $response = $this->httpClient->request('GET', self::GEOCODE_ENDPOINT, [
                'query' => [
                    'address' => $address,
                    'language' => 'fr-FR',
                    'key' => $this->google_maps_api_key,
                ],
            ]);

            $geo = $response->toArray(false);
            if (($geo['status'] ?? null) !== 'OK') {
                return null;
            }

            $firstResult = $geo['results'][0] ?? null;
            $location = $firstResult['geometry']['location'] ?? null;
            if (!is_array($location) || !isset($location['lat'], $location['lng'])) {
                return null;
            }

            $lat = $this->toFloat($location['lat']);
            $lng = $this->toFloat($location['lng']);
            if ($lat === null || $lng === null) {
                return null;
            }

            return [
                'lat' => $lat,
                'lng' => $lng,
                'place_id' => is_string($firstResult['place_id'] ?? null) ? $firstResult['place_id'] : null,
                'formatted_address' => is_string($firstResult['formatted_address'] ?? null) ? $firstResult['formatted_address'] : null,
            ];
        } catch (TransportExceptionInterface|\Throwable $exception) {
            $this->logger->error('Google Maps geocode failed', [
                'exception' => $exception->getMessage(),
                'address' => $address,
            ]);
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function decodeMatrixResponse(string $content): array
    {
        $trimmed = trim($content);
        if ($trimmed === '') {
            return [];
        }

        if (str_starts_with($trimmed, '[')) {
            $decoded = json_decode($trimmed, true);

            return is_array($decoded) ? $decoded : [];
        }

        $rows = [];
        $lines = preg_split('/\R/', $trimmed) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || !str_starts_with($line, '{')) {
                continue;
            }

            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $rows[] = $decoded;
            }
        }

        return $rows;
    }

    private function parseDurationToSeconds(mixed $duration): ?int
    {
        if (!is_string($duration)) {
            return null;
        }

        if (preg_match('/^([0-9]+(?:\.[0-9]+)?)s$/', $duration, $matches) !== 1) {
            return null;
        }

        return (int) round((float) $matches[1]);
    }

    private function mapTravelMode(string $mode): string
    {
        return match (strtoupper(trim($mode))) {
            'WALK', 'WALKING' => 'WALK',
            'BICYCLE', 'BICYCLING' => 'BICYCLE',
            'TRANSIT' => 'TRANSIT',
            'DRIVE', 'DRIVING' => 'DRIVE',
            default => 'DRIVE',
        };
    }

    private function formatDistance(int $distanceMeters): string
    {
        if ($distanceMeters < 1000) {
            return $distanceMeters . ' m';
        }

        $kilometers = $distanceMeters / 1000;
        $formatted = number_format($kilometers, 1, ',', ' ');
        if (str_ends_with($formatted, ',0')) {
            $formatted = substr($formatted, 0, -2);
        }

        return $formatted . ' km';
    }

    private function formatDuration(int $durationSeconds): string
    {
        $minutesTotal = (int) round($durationSeconds / 60);
        $hours = intdiv($minutesTotal, 60);
        $minutes = $minutesTotal % 60;

        if ($hours <= 0) {
            return max(1, $minutesTotal) . ' min';
        }

        if ($minutes === 0) {
            return $hours . ' h';
        }

        return sprintf('%d h %02d min', $hours, $minutes);
    }

    private function stripCountrySuffix(string $localization): string
    {
        return trim((string) preg_replace('/\s*-\s*[^-]+$/u', '', $localization));
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function extractCityAndZip(string $localization): array
    {
        if (preg_match('/^(.*?)\s*\((\d{5})\)/u', $localization, $matches) === 1) {
            return [trim($matches[1]), trim($matches[2])];
        }

        if (str_contains($localization, '(Département)') || str_contains($localization, '(Région)')) {
            return [null, null];
        }

        return [trim($localization), null];
    }

    private function extractDepartmentName(string $localization): ?string
    {
        if (!str_contains($localization, 'Département') && !str_contains($localization, 'Departement')) {
            return null;
        }

        return trim((string) preg_replace('/\s*\(D[ée]partement\).*$/ui', '', $localization));
    }

    private function extractRegionName(string $localization): ?string
    {
        if (!str_contains($localization, 'Région') && !str_contains($localization, 'Region')) {
            return null;
        }

        return trim((string) preg_replace('/\s*\(R[ée]gion\).*$/ui', '', $localization));
    }

    private function toFloat(mixed $value): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }
}
