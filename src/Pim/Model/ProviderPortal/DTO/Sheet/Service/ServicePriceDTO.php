<?php

namespace App\Pim\Model\ProviderPortal\DTO\Sheet\Service;

class ServicePriceDTO
{
    public float $perServicePrice = 0;

    public float $perPersonPrice = 0;

    public float $perDayPrice = 0;

    public float $perHalfDayPrice = 0;

    public float $perHourPrice = 0;

    public float $onDemandPrice = 0;

    public static function mock(): self
    {
        $data = new self();

        $data->perServicePrice = 100;
        $data->perPersonPrice = 50;
        $data->perDayPrice = 250;
        $data->perHalfDayPrice = 150;
        $data->perHourPrice = 50;
        $data->onDemandPrice = 120;

        return $data;
    }
}
