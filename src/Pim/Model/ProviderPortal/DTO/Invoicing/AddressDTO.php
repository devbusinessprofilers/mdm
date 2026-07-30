<?php

namespace App\Pim\Model\ProviderPortal\DTO\Invoicing;

class AddressDTO
{
    public ?string $street = null;

    public ?string $street2 = null;

    public ?string $zipCode = null;

    public ?string $city = null;

    public ?string $country = null;

    public static function mock(): self
    {
        $data = new self();

        $data->street = '6 rue Victor Hugo';
        $data->street2 = '';
        $data->zipCode = '60500';
        $data->city = 'Gouvieux';
        $data->country = 'FR';

        return $data;
    }
}
