<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\MealTray;

class DelayLimitChoices
{
    /**
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        return [
            '1h' => '1h',
            '2h' => '2h',
            '3h' => '3h',
            '4h' => '4h',
            '5h' => '5h',
        ];
    }
}
