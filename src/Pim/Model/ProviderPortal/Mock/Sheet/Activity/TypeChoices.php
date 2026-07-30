<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\Activity;

class TypeChoices
{
    /**
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        return [
            'Intérieur' => 'interieur',
            'Extérieur' => 'exterieur',
        ];
    }
}
