<?php

namespace App\Pim\Model\ProviderPortal\DTO\Date;

class OpeningHoursDTO
{
    public WeekDayOpeningHoursDTO $monday;

    public WeekDayOpeningHoursDTO $tuesday;

    public WeekDayOpeningHoursDTO $wednesday;

    public WeekDayOpeningHoursDTO $thursday;

    public WeekDayOpeningHoursDTO $friday;

    public WeekDayOpeningHoursDTO $saturday;

    public WeekDayOpeningHoursDTO $sunday;

    public function __construct()
    {
        $this->monday = new WeekDayOpeningHoursDTO();
        $this->tuesday = new WeekDayOpeningHoursDTO();
        $this->wednesday = new WeekDayOpeningHoursDTO();
        $this->thursday = new WeekDayOpeningHoursDTO();
        $this->friday = new WeekDayOpeningHoursDTO();
        $this->saturday = new WeekDayOpeningHoursDTO();
        $this->sunday = new WeekDayOpeningHoursDTO();
    }

    public static function mock(): self
    {
        $data = new self();

        $data->monday = WeekDayOpeningHoursDTO::mock(true);
        $data->tuesday = WeekDayOpeningHoursDTO::mock(true);
        $data->wednesday = WeekDayOpeningHoursDTO::mock(true);
        $data->thursday = WeekDayOpeningHoursDTO::mock(true);
        $data->friday = WeekDayOpeningHoursDTO::mock(true);
        $data->saturday = WeekDayOpeningHoursDTO::mock(false);
        $data->sunday = WeekDayOpeningHoursDTO::mock(false);

        return $data;
    }
}
