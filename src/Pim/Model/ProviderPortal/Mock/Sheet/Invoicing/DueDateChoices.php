<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\Invoicing;

class DueDateChoices
{
    /**
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        return [
            '7 Jours' => '7-jours',
            '15 Jours' => '15-jours',
            '30 Jours' => '30-jours',
            '45 Jours' => '45-jours',
            '60 Jours' => '60-jours',
        ];
    }
}
