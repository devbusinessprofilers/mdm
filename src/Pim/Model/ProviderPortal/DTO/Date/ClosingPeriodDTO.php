<?php

namespace App\Pim\Model\ProviderPortal\DTO\Date;

class ClosingPeriodDTO
{
    public ?string $name = null;

    public ?DateRangeDTO $period = null;

    public static function mock(?string $name = null, ?string $from = null, ?string $to = null): self
    {
        $data = new self();

        $data->name = $name ?? 'Période de vacances';
        $data->period = new DateRangeDTO(
            new \DateTime($from ?? '+10 days'),
            new \DateTime($to ?? '+25 days'),
        );

        return $data;
    }
}
