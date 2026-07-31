<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\Restaurant;

class DietaryPreferenceChoices
{
    /**
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        return [
            'Casher' => 'casher',
            'Halal' => 'halal',
            'Vegan' => 'vegan',
            'Végétariennes' => 'vegetariennes',
            'Plats bio' => 'plats-bio',
            'Produits locaux' => 'produits-locaux',
        ];
    }
}
