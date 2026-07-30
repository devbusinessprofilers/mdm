<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\Place;

class CuisineChoices
{
    /**
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        return [
            'Gastronomique' => 'gastronomique',
            'Bistrot' => 'bistrot',
            'Fusion' => 'fusion',
            'Internationale' => 'internationale',
            'Traditionnelle' => 'traditionnelle',
            'Cuisine événementielle' => 'cuisine-evenementielle',
            'Étoile Michelin' => 'etoile-michelin',
            'Economique' => 'economique',
        ];
    }
}
