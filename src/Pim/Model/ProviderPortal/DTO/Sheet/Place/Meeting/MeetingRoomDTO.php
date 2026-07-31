<?php

namespace App\Pim\Model\ProviderPortal\DTO\Sheet\Place\Meeting;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class MeetingRoomDTO
{
    public ?string $name = null;

    public ?int $area = null;

    public ?int $theatreArea = null;

    public ?int $meetingArea = null;

    public ?int $uShapedArea = null;

    public ?int $classArea = null;

    public ?int $cabaretArea = null;

    public ?int $banquetArea = null;

    public ?int $cocktailArea = null;

    public bool $hasNaturalLight = false;

    public bool $hasAirConditioned = false;

    public bool $hasReducedMobilityAccess = false;

    public bool $hasDanceFloor = false;

    public ?string $pictureUrl = null;

    public ?UploadedFile $pictureFile = null;

    public ?string $planUrl = null;

    public ?UploadedFile $planFile = null;

    public int $position = 1;

    public static function mock(int $index = 1): self
    {
        $data = new self();

        $data->name = 'Salle '.$index;
        $data->area = 32;
        $data->theatreArea = 32;
        $data->meetingArea = 32;
        $data->uShapedArea = 32;
        $data->classArea = 32;
        $data->banquetArea = 32;
        $data->cocktailArea = 32;
        $data->hasNaturalLight = true;
        $data->hasAirConditioned = false;
        $data->hasReducedMobilityAccess = true;
        $data->hasDanceFloor = false;
        $data->pictureUrl = '/provider_portal/img/mock/picture.jpg';
        $data->planUrl = '/provider_portal/img/mock/document.pdf';
        $data->position = $index;

        return $data;
    }

    public function getPlanFileName(): ?string
    {
        if (null === $this->planUrl) {
            return null;
        }

        return basename($this->planUrl);
    }
}
