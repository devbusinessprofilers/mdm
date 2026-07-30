<?php

namespace App\Pim\Model\ProviderPortal\DTO\Sheet\Activity;

use App\Pim\Model\ProviderPortal\Mock\Sheet\Activity\EsatProviderChoices;

class ActivityCsrDTO
{
    /**
     * @var array<string>
     */
    public array $esatProviders = [];

    public static function mock(): self
    {
        $data = new self();

        $data->esatProviders = array_unique([
            array_rand(array_flip(EsatProviderChoices::getChoices())),
            array_rand(array_flip(EsatProviderChoices::getChoices())),
        ]);

        return $data;
    }
}
