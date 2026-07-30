<?php

namespace App\Pim\Model\ProviderPortal\DTO\Date;

class WeekDayOpeningHoursDTO
{
    public ?string $from = null;

    public ?string $to = null;

    public bool $isOpen = false;

    public static function mock(bool $isOpen, ?string $from = null, ?string $to = null): self
    {
        $data = new self();

        $data->isOpen = $isOpen;
        $data->from = $isOpen ? ($from ?? '09:00') : null;
        $data->to = $isOpen ? ($to ?? '18:00') : null;

        return $data;
    }
}
