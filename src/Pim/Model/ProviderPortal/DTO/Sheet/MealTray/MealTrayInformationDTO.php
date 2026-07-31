<?php

namespace App\Pim\Model\ProviderPortal\DTO\Sheet\MealTray;

use App\Pim\Model\ProviderPortal\DTO\Date\ClosingPeriodDTO;
use App\Pim\Model\ProviderPortal\DTO\Date\OpeningHoursDTO;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class MealTrayInformationDTO
{
    public ?string $name = null;

    public ?UploadedFile $pictureFile = null;

    public ?string $pictureUrl = null;

    public ?UploadedFile $logoFile = null;

    public ?string $logoUrl = null;

    public OpeningHoursDTO $openingHours;

    /**
     * @var array<ClosingPeriodDTO>
     */
    public array $closingPeriods = [];

    public static function mock(): self
    {
        $data = new self();

        $data->name = 'Traiteur asiatique';
        $data->pictureUrl = '/provider_portal/img/mock/picture.jpg';

        $data->openingHours = OpeningHoursDTO::mock();

        $data->closingPeriods = [
            ClosingPeriodDTO::mock('Vacances avant', '-2 months', '-1 month'),
            ClosingPeriodDTO::mock('Vacances apres', '+1 month', '+2 months'),
        ];

        return $data;
    }
}
