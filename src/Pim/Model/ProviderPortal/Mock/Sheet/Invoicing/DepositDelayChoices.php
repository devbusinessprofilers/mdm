<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\Invoicing;

class DepositDelayChoices
{
    /**
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        return [
            'Signature à J-61' => 'signature-a-j-61',
            'J-60 à J-30' => 'j-60-a-j-30',
            'J-29 à J-8' => 'j-29-a-j-8',
            'Après J-7' => 'apres-j-7',
        ];
    }
}
