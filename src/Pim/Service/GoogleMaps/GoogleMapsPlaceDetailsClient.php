<?php

namespace App\Pim\Service\GoogleMaps;

use App\Pim\Model\ProviderPortal\DTO\Localisation\AddressDTO;
use App\Pim\Model\ProviderPortal\DTO\Localisation\CoordinatesDTO;
use App\Pim\Service\Localisation\PlaceDetailsClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class GoogleMapsPlaceDetailsClient implements GoogleMapsPlacesClientInterface, PlaceDetailsClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $googleMapPlacesClient,
        private readonly LocaleSwitcher $localeSwitcher,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getAddress(string $placeId): ?AddressDTO
    {
        $response = $this->googleMapPlacesClient->request(
            'GET',
            \sprintf('/v1/places/%s', $placeId),
            [
                'headers' => $this->buildHeaders([
                    'X-Goog-FieldMask' => 'addressComponents,location',
                ]),
            ]
        );

        return $this->extractAddress($response);
    }

    private function buildHeaders(array $options = []): array
    {
        return [
            'Accept-Language' => $this->localeSwitcher->getLocale(),
            ...$options,
        ];
    }

    private function extractAddress(ResponseInterface $response): ?AddressDTO
    {
        if (200 !== $response->getStatusCode()) {
            $this->logger->error('Google Maps place details error', [
                'status_code' => $response->getStatusCode(),
            ]);

            return null;
        }

        $data = $response->toArray();
        if (!isset($data['addressComponents'])) {
            return null;
        }

        $address = new AddressDTO();
        $streetNumber = null;
        $route = null;

        foreach ($data['addressComponents'] as $component) {
            $types = $component['types'] ?? [];

            foreach ($types as $type) {
                match ($type) {
                    self::PLACE_TYPE_COUNTRY => $address->setCountry($component['shortText']),
                    self::PLACE_TYPE_CITY => $address->setCity($component['longText']),
                    self::PLACE_TYPE_ZIP_CODE => $address->setZipCode($component['longText']),
                    self::PLACE_TYPE_NUMBER => $streetNumber = $component['longText'],
                    self::PLACE_TYPE_STREET => $route = $component['longText'],
                    self::PLACE_TYPE_AREA => $address->setArea($component['longText']),
                    self::PLACE_TYPE_DEPARTMENT => $address->setDepartment($component['longText']),
                    self::PLACE_TYPE_DISTRICT => $address->setDistrict($component['longText']),
                    default => null,
                };
            }
        }

        if (null !== $route) {
            $address->setStreet(
                trim(sprintf('%s %s', $streetNumber, $route))
            );
        }

        $location = $data['location'] ?? null;
        if ($location && \is_array($location)) {
            $address->setPosition(new CoordinatesDTO($location['latitude'], $location['longitude']));
        }

        return $address;
    }
}
