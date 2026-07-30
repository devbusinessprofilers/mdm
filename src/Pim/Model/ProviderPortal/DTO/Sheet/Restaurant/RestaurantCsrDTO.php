<?php

namespace App\Pim\Model\ProviderPortal\DTO\Sheet\Restaurant;

use App\Pim\Model\ProviderPortal\Mock\Sheet\Restaurant\CSRCommitmentChoices;

class RestaurantCsrDTO
{
    public array $csrCommitments = [];

    public static function mock(): self
    {
        $data = new self();

        $data->csrCommitments = [array_rand(array_flip(CSRCommitmentChoices::getChoices()))];

        return $data;
    }
}
