<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\Place;

class EquipmentChoices
{
    /**
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        return [
            'Machine à café' => 'machine-a-cafe',
            'Vue' => 'vue',
            'Télévision' => 'television',
            'Balcon' => 'balcon',
            'Climatisation' => 'climatisation',
            'Bureau' => 'bureau',
            'Baignoire' => 'baignoire',
        ];
    }
}
