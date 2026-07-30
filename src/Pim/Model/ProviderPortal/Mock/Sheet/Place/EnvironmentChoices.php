<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\Place;

class EnvironmentChoices
{
    /**
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        return [
            'Mer' => 'mer',
            'Au Vert' => 'au-vert',
            'Campagne' => 'campagne',
            'Montagne' => 'montagne',
            'Lac' => 'lac',
            'Centre Ville' => 'centre-ville',
        ];
    }
}
