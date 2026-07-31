<?php

namespace App\Pim\Model\ProviderPortal\DTO\Sheet\Place;

use App\Pim\Model\ProviderPortal\DTO\Date\ClosingPeriodDTO;
use App\Pim\Model\ProviderPortal\DTO\Date\OpeningHoursDTO;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Place\EventChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Place\TypologyChoices;

class PlaceGeneralDataDTO
{
    public ?string $name = null;

    /**
     * @var array<string>
     */
    public array $typologies = [];

    /**
     * @var array<string>
     */
    public array $groups = [];

    /**
     * @var array<string>
     */
    public array $events = [];

    public ?string $erp = null;

    public ?string $website = null;

    public bool $privatisable = false;

    public OpeningHoursDTO $openingHours;

    /**
     * @var array<ClosingPeriodDTO>
     */
    public array $closingPeriods = [];

    public static function mock(): self
    {
        $data = new self();

        $data->name = 'Jeanne & The Forest - Château de Montvillargenne';
        $data->typologies = array_rand(array_flip(TypologyChoices::getChoices()), 2);
        $data->events = array_rand(array_flip(EventChoices::getChoices()), 2);
        $data->website = 'https://www.jeanneandtheforest.com/seminaire';

        $data->privatisable = true;
        $data->openingHours = OpeningHoursDTO::mock();

        $data->closingPeriods = [
            ClosingPeriodDTO::mock('Vacances avant', '-2 months', '-1 month'),
            ClosingPeriodDTO::mock('Vacances apres', '+1 month', '+2 months'),
        ];

        return $data;
    }
}
