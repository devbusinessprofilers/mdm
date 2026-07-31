<?php

namespace App\Pim\Model\ProviderPortal\DTO\Sheet\Restaurant;

use App\Pim\Model\ProviderPortal\DTO\Date\ClosingPeriodDTO;
use App\Pim\Model\ProviderPortal\DTO\Date\OpeningHoursDTO;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Restaurant\CuisineChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Restaurant\DietaryPreferenceChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Restaurant\EventChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Restaurant\TypologyChoices;

class RestaurantInformationDTO
{
    public ?string $name = null;

    /**
     * @var array<string>
     */
    public array $typologies = [];

    /**
     * @var array<string>
     */
    public array $cuisines = [];

    /**
     * @var array<string>
     */
    public array $dietaryPreferences = [];

    /**
     * @var array<string>
     */
    public array $events = [];

    public ?string $website = null;

    public bool $totalExclusivityAuthorized = false;

    public bool $partialExclusivityAuthorized = false;

    public OpeningHoursDTO $openingHours;

    /**
     * @var array<ClosingPeriodDTO>
     */
    public array $closingPeriods = [];

    public static function mock(): self
    {
        $data = new self();

        $data->name = 'Restaurant Villa M';

        $data->typologies = array_unique([
            array_rand(array_flip(TypologyChoices::getChoices())),
            array_rand(array_flip(TypologyChoices::getChoices())),
        ]);

        $data->cuisines = array_unique([
            array_rand(array_flip(CuisineChoices::getChoices())),
            array_rand(array_flip(CuisineChoices::getChoices())),
        ]);

        $data->dietaryPreferences = array_unique([
            array_rand(array_flip(DietaryPreferenceChoices::getChoices())),
            array_rand(array_flip(DietaryPreferenceChoices::getChoices())),
        ]);

        $data->events = array_unique([
            array_rand(array_flip(EventChoices::getChoices())),
            array_rand(array_flip(EventChoices::getChoices())),
        ]);

        $data->website = 'https://www.jeanneandtheforest.com/seminaire';

        $data->partialExclusivityAuthorized = true;

        $data->openingHours = OpeningHoursDTO::mock();

        $data->closingPeriods = [
            ClosingPeriodDTO::mock('Vacances avant', '-2 months', '-1 month'),
            ClosingPeriodDTO::mock('Vacances apres', '+1 month', '+2 months'),
        ];

        return $data;
    }
}
