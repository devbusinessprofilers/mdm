<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\Invoicing;

class VatModeChoices
{
    /**
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        return [
            'Débit' => 'debit',
            'Encaissement' => 'encaissement',
        ];
    }
}
