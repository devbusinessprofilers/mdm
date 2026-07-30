<?php

namespace App\Pim\Model\ProviderPortal\DTO\Sheet\Place;

use App\Pim\Model\ProviderPortal\Mock\Sheet\Place\AmenityTagOptions;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Place\FacilityTagOptions;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Place\MeetingEquipmentTagOptions;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Place\WellnessTagOptions;

class PlaceServicesDTO
{
    public array $amenities = [];

    public array $meetingEquipments = [];

    public array $facilities = [];

    public array $wellnessList = [];

    public static function mock(): self
    {
        $data = new self();

        $data->amenities = [
            AmenityTagOptions::getTagOptions()[1]->value,
            AmenityTagOptions::getTagOptions()[2]->value,
        ];
        $data->meetingEquipments = [
            MeetingEquipmentTagOptions::getTagOptions()[1]->value,
            MeetingEquipmentTagOptions::getTagOptions()[2]->value,
        ];
        $data->facilities = [
            FacilityTagOptions::getTagOptions()[1]->value,
            FacilityTagOptions::getTagOptions()[2]->value,
        ];
        $data->wellnessList = [
            WellnessTagOptions::getTagOptions()[1]->value,
            WellnessTagOptions::getTagOptions()[2]->value,
        ];

        return $data;
    }
}
