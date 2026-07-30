<?php

namespace App\Pim\Service\Localisation;

use App\Pim\Model\ProviderPortal\DTO\Localisation\SuggestionDTO;

interface AutocompletePlaceClientInterface
{
    /**
     * @return SuggestionDTO[]
     */
    public function autocompleteStreet(string $input, string $country): array;

    /**
     * @return SuggestionDTO[]
     */
    public function autocompleteZipCode(string $input, string $country): array;

    /**
     * @return SuggestionDTO[]
     */
    public function autocompleteDepartment(string $input, string $country): array;

    /**
     * @return SuggestionDTO[]
     */
    public function autocompleteDistrict(string $input, string $country): array;

    /**
     * @return SuggestionDTO[]
     */
    public function autocompleteArea(string $input, string $country): array;

    /**
     * @return SuggestionDTO[]
     */
    public function autocompleteCity(string $input, string $country): array;
}
