<?php

namespace App\Pim\Model\ProviderPortal\DTO\Sheet\Restaurant;

class RestaurantCapacityDTO
{
    public int $sittingCapacity = 0;

    public int $privateCapacity = 0;

    public int $feastCapacity = 0;

    public int $cocktailCapacity = 0;

    public static function mock(): self
    {
        $data = new self();

        $data->sittingCapacity = 100;
        $data->privateCapacity = 50;
        $data->feastCapacity = 120;
        $data->cocktailCapacity = 200;

        return $data;
    }
}
