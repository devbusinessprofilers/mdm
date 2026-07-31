<?php

namespace App\Pim\Model\ProviderPortal\DTO\Sheet\Restaurant;

use App\Pim\Model\ProviderPortal\Mock\Sheet\Restaurant\FacilityChoices;

class RestaurantFacilityDTO
{
    /**
     * @var array<string>
     */
    public array $services = [];

    public bool $withDancingFloor = false;

    public bool $withWifi = false;

    public bool $withPrivacyMediaRoom = false;

    public bool $withMicrophone = false;

    public bool $withOnSiteParking = false;

    public bool $withNearbyParking = false;

    public bool $withValetParking = false;

    public static function mock(): self
    {
        $data = new self();

        $data->services = array_unique([
            array_rand(array_flip(FacilityChoices::getChoices())),
            array_rand(array_flip(FacilityChoices::getChoices())),
        ]);

        $data->withDancingFloor = true;
        $data->withWifi = true;
        $data->withPrivacyMediaRoom = true;
        $data->withOnSiteParking = true;
        $data->withValetParking = true;

        return $data;
    }
}
