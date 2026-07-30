<?php

namespace App\Pim\Service\GoogleMaps;

use App\Pim\Model\ProviderPortal\DTO\Localisation\SuggestionDTO;
use App\Pim\Service\Localisation\AutocompletePlaceClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class GoogleMapsAutocompletePlacesClient implements GoogleMapsPlacesClientInterface, AutocompletePlaceClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $googleMapPlacesClient,
        private readonly LocaleSwitcher $localeSwitcher,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return SuggestionDTO[]
     */
    public function autocompleteStreet(string $input, string $country): array
    {
        return $this->autocomplete($country, [
            'input' => $input,
            'includedPrimaryTypes' => [self::PLACE_TYPE_FULL_ADDRESS],
            ...($country ? ['includedRegionCodes' => [$country]] : []),
        ]);
    }

    /**
     * @return SuggestionDTO[]
     */
    public function autocompleteZipCode(string $input, string $country): array
    {
        return $this->autocomplete($country, [
            'input' => $input,
            'includedPrimaryTypes' => [self::PLACE_TYPE_ZIP_CODE],
            ...($country ? ['includedRegionCodes' => [$country]] : []),
        ]);
    }

    /**
     * @return SuggestionDTO[]
     */
    public function autocompleteDepartment(string $input, string $country): array
    {
        return $this->autocomplete($country, [
            'input' => $input,
            'includedPrimaryTypes' => [self::PLACE_TYPE_DEPARTMENT],
            ...($country ? ['includedRegionCodes' => [$country]] : []),
        ]);
    }

    /**
     * @return SuggestionDTO[]
     */
    public function autocompleteDistrict(string $input, string $country): array
    {
        return $this->autocomplete($country, [
            'input' => $input,
            'includedPrimaryTypes' => [self::PLACE_TYPE_DISTRICT],
            ...($country ? ['includedRegionCodes' => [$country]] : []),
        ]);
    }

    /**
     * @return SuggestionDTO[]
     */
    public function autocompleteArea(string $input, string $country): array
    {
        return $this->autocomplete($country, [
            'input' => $input,
            'includedPrimaryTypes' => [self::PLACE_TYPE_AREA],
            ...($country ? ['includedRegionCodes' => [$country]] : []),
        ]);
    }

    /**
     * @return SuggestionDTO[]
     */
    public function autocompleteCity(string $input, string $country): array
    {
        return $this->autocomplete($country, [
            'input' => $input,
            'includedPrimaryTypes' => [self::PLACE_TYPE_CITY],
            ...($country ? ['includedRegionCodes' => [$country]] : []),
        ]);
    }

    /**
     * @return SuggestionDTO[]
     */
    private function autocomplete(string $country, array $options = []): array
    {
        $response = $this->googleMapPlacesClient->request(
            'POST',
            '/v1/places:autocomplete',
            [
                'headers' => $this->buildHeaders($options['headers'] ?? []),
                'json' => [
                    'regionCode' => $country,
                    ...$options,
                ],
            ]
        );

        return $this->formatResponse($response);
    }

    private function buildHeaders(array $options = []): array
    {
        return [
            'Accept-Language' => $this->localeSwitcher->getLocale(),
            ...$options,
        ];
    }

    /**
     * @return SuggestionDTO[]
     */
    private function formatResponse(ResponseInterface $response): array
    {
        if (200 !== $response->getStatusCode()) {
            $this->logger->error('Google Maps autocomplete place error', [
                'status_code' => $response->getStatusCode(),
            ]);

            return [];
        }

        $data = $response->toArray();

        $suggestions = [];
        $rawSuggestions = $data['suggestions'] ?? [];
        foreach ($rawSuggestions as $rawSuggestion) {
            $prediction = $rawSuggestion['placePrediction'] ?? null;
            if (!$prediction || !\is_array($prediction)) {
                continue;
            }

            $id = $prediction['placeId'] ?? null;
            if (!$id) {
                continue;
            }

            $suggestion = new SuggestionDTO($id);

            $text = $prediction['text'] ?? null;
            if ($text && \is_array($text)) {
                $suggestion->setLabel($text['text'] ?? null);
            }

            $suggestions[] = $suggestion;
        }

        return $suggestions;
    }
}
