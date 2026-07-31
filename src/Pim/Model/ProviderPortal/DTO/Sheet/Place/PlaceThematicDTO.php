<?php

namespace App\Pim\Model\ProviderPortal\DTO\Sheet\Place;

use App\Pim\Model\ProviderPortal\Mock\Sheet\Place\AtmosphereChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Place\EnvironmentChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Place\ThematicChoices;

class PlaceThematicDTO
{
    public array $thematic = [];

    public array $environment = [];

    public array $atmosphere = [];

    public static function mock(): self
    {
        $data = new self();

        $data->thematic = array_unique([
            array_rand(array_flip(ThematicChoices::getChoices())),
            array_rand(array_flip(ThematicChoices::getChoices())),
        ]);
        $data->environment = array_unique([
            array_rand(array_flip(EnvironmentChoices::getChoices())),
            array_rand(array_flip(EnvironmentChoices::getChoices())),
        ]);
        $data->atmosphere = array_unique([
            array_rand(array_flip(AtmosphereChoices::getChoices())),
            array_rand(array_flip(AtmosphereChoices::getChoices())),
        ]);

        return $data;
    }
}
