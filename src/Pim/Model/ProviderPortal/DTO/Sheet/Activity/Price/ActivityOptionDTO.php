<?php

namespace App\Pim\Model\ProviderPortal\DTO\Sheet\Activity\Price;

class ActivityOptionDTO
{
    public ?string $name = null;

    public ?string $capacity = null;

    public ?string $price = null;

    public static function mock(): self
    {
        $data = new self();

        $data->name = 'Option A';
        $data->capacity = '2 à 5 personnes';
        $data->price = '50€ tout compris';

        return $data;
    }
}
