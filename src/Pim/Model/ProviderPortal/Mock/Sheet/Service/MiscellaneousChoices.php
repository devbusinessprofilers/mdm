<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\Service;

class MiscellaneousChoices
{
    /**
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        return [
            'Expériences exclusives personnalisées' => 'experiences-exclusives-personnalisees',
            'Services atypiques ou innovants' => 'services-atypiques-ou-innovants',
        ];
    }
}
