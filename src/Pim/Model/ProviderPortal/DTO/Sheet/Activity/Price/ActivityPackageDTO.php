<?php

namespace App\Pim\Model\ProviderPortal\DTO\Sheet\Activity\Price;

class ActivityPackageDTO
{
    public ?string $name = null;

    public ?string $capacity = null;

    public ?string $price = null;

    public static function mock(): self
    {
        $data = new self();

        $data->name = 'Forfait A';
        $data->capacity = 'Entre 5 et 10 personnes';
        $data->price = '10€ / pers.';

        return $data;
    }
}
