<?php

namespace App\Pim\Model\ProviderPortal\DTO\Invoicing;

class CancellationDTO
{
    public int $frame1 = 0;
    public int $frame2 = 0;
    public int $frame3 = 0;
    public int $frame4 = 0;
    public int $frame5 = 0;
    public int $frame6 = 0;
    public int $frame7 = 0;
    public int $frame8 = 0;
    public int $frame9 = 0;
    public int $frame10 = 0;

    public static function mock(): self
    {
        $data = new self();

        $data->frame4 = 10;
        $data->frame5 = 30;
        $data->frame6 = $data->frame7 = $data->frame8 = 50;
        $data->frame9 = 80;
        $data->frame10 = 100;

        return $data;
    }
}
