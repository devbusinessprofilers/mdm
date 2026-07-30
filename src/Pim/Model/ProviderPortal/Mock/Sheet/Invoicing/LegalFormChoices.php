<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\Invoicing;

class LegalFormChoices
{
    /**
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        return [
            'EI' => 'ei',
            'EURL' => 'eurl',
            'SARL' => 'sarl',
            'SASU' => 'sasu',
            'SAS' => 'sas',
            'SA' => 'sa',
            'SNC' => 'snc',
            'SCS' => 'scs',
            'SCA' => 'sca',
        ];
    }
}
