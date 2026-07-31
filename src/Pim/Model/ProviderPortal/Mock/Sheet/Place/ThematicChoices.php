<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\Place;

class ThematicChoices
{
    /**
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        return [
            'Bien-être' => 'bien-etre',
            'Golf' => 'golf',
            'Eco Responsable' => 'eco-responsable',
            'Gastronomique' => 'gastronomique',
            'Oenotourisme' => 'oenotourisme',
            'Ski' => 'ski',
            'Château' => 'chateau',
            'Comme à la maison' => 'comme-a-la-maison',
        ];
    }
}
