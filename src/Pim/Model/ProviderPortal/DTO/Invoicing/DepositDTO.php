<?php

namespace App\Pim\Model\ProviderPortal\DTO\Invoicing;

use App\Pim\Model\ProviderPortal\Mock\Sheet\Invoicing\DepositDelayChoices;

class DepositDTO
{
    public ?string $delay = null;

    public ?int $percent = null;

    public static function mock(): self
    {
        $data = new self();

        $data->delay = array_rand(array_flip(DepositDelayChoices::getChoices()));
        $data->percent = 20;

        return $data;
    }
}
