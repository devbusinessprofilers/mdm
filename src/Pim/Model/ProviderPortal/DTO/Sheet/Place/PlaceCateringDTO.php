<?php

namespace App\Pim\Model\ProviderPortal\DTO\Sheet\Place;

use App\Pim\Model\ProviderPortal\Mock\Sheet\Place\CuisineChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Place\MealServiceChoices;

class PlaceCateringDTO
{
    public int $restaurantCount = 0;

    public int $diningRoomCount = 0;

    public int $overallDiningCount = 0;

    public int $sittingDiningCount = 0;

    public bool $withDanceEvening = false;

    public bool $withCocktailParty = false;

    public bool $withLocalCaterer = false;

    public bool $withExternalCaterer = false;

    public bool $selfCatererAuthorized = false;

    public bool $exclusivityAuthorized = false;

    public ?string $musicEndTime = null;

    public array $cuisines = [];

    public array $mealServices = [];

    public static function mock(): self
    {
        $data = new self();

        $data->restaurantCount = 2;
        $data->diningRoomCount = 4;
        $data->overallDiningCount = 100;
        $data->sittingDiningCount = 80;
        $data->withDanceEvening = false;
        $data->withCocktailParty = true;
        $data->withLocalCaterer = true;
        $data->withExternalCaterer = false;
        $data->selfCatererAuthorized = true;
        $data->exclusivityAuthorized = true;
        $data->musicEndTime = '23:50';

        $data->cuisines = array_unique([
            array_rand(array_flip(CuisineChoices::getChoices())),
            array_rand(array_flip(CuisineChoices::getChoices())),
        ]);

        $data->mealServices = array_unique([
            array_rand(array_flip(MealServiceChoices::getChoices())),
            array_rand(array_flip(MealServiceChoices::getChoices())),
            array_rand(array_flip(MealServiceChoices::getChoices())),
        ]);

        return $data;
    }
}
