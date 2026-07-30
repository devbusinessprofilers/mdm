<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\MealTray;

class MediaCategoryChoices
{
    /**
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        return [
            'Photos principales' => 'photos-principales',
            'Plats végétariens' => 'plats-vegetariens',
            'Boissons' => 'boissons',
            'Produits' => 'produits',
        ];
    }
}
