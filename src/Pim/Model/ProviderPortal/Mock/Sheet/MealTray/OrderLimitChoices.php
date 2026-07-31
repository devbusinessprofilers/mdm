<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\MealTray;

class OrderLimitChoices
{
    /**
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        return [
            'Jour même' => 'jour-meme',
            'Veille' => 'veille',
            'Avant-veille' => 'avant-veille',
        ];
    }
}
