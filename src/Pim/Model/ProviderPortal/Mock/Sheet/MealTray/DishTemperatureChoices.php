<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\MealTray;

class DishTemperatureChoices
{
    /**
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        return [
            'Livraison chaude' => 'livraison-chaude',
            'Livraison froide' => 'livraison-froide',
            'À réchauffer' => 'a-rechauffer',
        ];
    }
}
