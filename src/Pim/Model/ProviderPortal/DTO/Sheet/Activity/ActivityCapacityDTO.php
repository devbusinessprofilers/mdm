<?php

namespace App\Pim\Model\ProviderPortal\DTO\Sheet\Activity;

class ActivityCapacityDTO
{
    public int $minCapacity = 0;

    public int $maxCapacity = 0;

    public int $minDuration = 0;

    public int $maxDuration = 0;

    public static function mock(): self
    {
        $data = new self();

        $data->minCapacity = 50;
        $data->maxCapacity = 50;
        $data->minDuration = 60;
        $data->maxDuration = 180;

        return $data;
    }
}
