<?php

namespace App\Pim\Model\ProviderPortal\DTO\Sheet\Place;

use App\Pim\Model\ProviderPortal\Mock\Sheet\Place\EquipmentChoices;

class PlaceAccommodationDTO
{
    public bool $withAccommodation = false;

    public ?int $roomCount = null;

    public ?int $singleRoomCount = null;

    public ?int $twinRoomCount = null;

    public ?int $doubleRoomCount = null;

    public ?int $totalCapacity = null;

    public ?string $description = null;

    public array $equipments = [];

    public static function mock(): self
    {
        $data = new self();

        $data->withAccommodation = true;
        $data->roomCount = 10;
        $data->singleRoomCount = 5;
        $data->twinRoomCount = 3;
        $data->doubleRoomCount = 2;
        $data->totalCapacity = 15;
        $data->description = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.';

        $data->equipments = array_unique([
            array_rand(array_flip(EquipmentChoices::getChoices())),
            array_rand(array_flip(EquipmentChoices::getChoices())),
        ]);

        return $data;
    }
}
