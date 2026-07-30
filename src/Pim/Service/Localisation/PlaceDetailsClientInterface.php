<?php

namespace App\Pim\Service\Localisation;

use App\Pim\Model\ProviderPortal\DTO\Localisation\AddressDTO;

interface PlaceDetailsClientInterface
{
    /**
     * @return Address
     */
    public function getAddress(string $placeId): ?AddressDTO;
}
