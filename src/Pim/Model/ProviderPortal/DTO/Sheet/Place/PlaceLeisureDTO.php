<?php

namespace App\Pim\Model\ProviderPortal\DTO\Sheet\Place;

use App\Pim\Model\ProviderPortal\Mock\Sheet\Place\LeisureChoices;

class PlaceLeisureDTO
{
    /**
     * @var array<string>
     */
    public array $leisureList = [];

    /**
     * @var array<TeamBuildingDTO>
     */
    public array $teamBuildings = [];

    public static function mock(): self
    {
        $data = new self();

        $data->leisureList = array_unique([
            array_rand(array_flip(LeisureChoices::getChoices())),
            array_rand(array_flip(LeisureChoices::getChoices())),
            array_rand(array_flip(LeisureChoices::getChoices())),
        ]);

        $data->teamBuildings = [
            TeamBuildingDTO::mock(),
            TeamBuildingDTO::mock(),
        ];

        return $data;
    }
}
