<?php

namespace App\Pim\Model\ProviderPortal\DTO\Sheet\Place;

use App\Pim\Model\ProviderPortal\DTO\Sheet\Place\Meeting\MeetingRoomDTO;

class PlaceMeetingDTO
{
    public bool $hasMeetingRooms = false;

    public ?int $meetingRoomNumber = null;

    public ?int $cocktailConfigurationCapacity = null;

    public ?int $theatreConfigurationCapacity = null;

    public ?int $minRoomArea = null;

    public ?int $maxRoomArea = null;

    public ?string $description = null;

    /**
     * @var array<MeetingRoomDTO>
     */
    public array $meetingRooms = [];

    public static function mock(): self
    {
        $data = new self();
        $data->hasMeetingRooms = true;
        $data->meetingRoomNumber = 1;
        $data->cocktailConfigurationCapacity = 32;
        $data->theatreConfigurationCapacity = 32;
        $data->minRoomArea = 32;
        $data->maxRoomArea = 32;
        $data->description = 'Lorem ipsum dolor sit amet consectetur.';

        $meetingRooms = [
            MeetingRoomDTO::mock(),
            MeetingRoomDTO::mock(2),
        ];

        usort($meetingRooms, fn (MeetingRoomDTO $a, MeetingRoomDTO $b) => $a->position <=> $b->position);

        $data->meetingRooms = $meetingRooms;

        return $data;
    }
}
