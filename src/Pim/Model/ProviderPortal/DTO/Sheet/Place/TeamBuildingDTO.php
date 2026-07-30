<?php

namespace App\Pim\Model\ProviderPortal\DTO\Sheet\Place;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class TeamBuildingDTO
{
    public ?string $contractor = null;

    public ?string $activity = null;

    public ?string $pictureUrl = null;

    public ?UploadedFile $pictureFile = null;

    public static function mock(): self
    {
        $data = new self();

        $data->contractor = 'Société de team building';
        $data->activity = 'Activité de team building';

        return $data;
    }
}
