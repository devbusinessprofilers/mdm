<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\Place;

class MediaCategoryChoices
{
    /**
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        return [
            'Photos principales' => 'photos-principales',
            'Façades' => 'facades',
            'Chambres' => 'chambres',
            'Restauration' => 'restauration',
            'Salles de réunions' => 'salles-de-reunions',
            'Divers' => 'divers',
        ];
    }
}
