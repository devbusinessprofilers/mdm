<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\Restaurant;

class MediaCategoryChoices
{
    /**
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        return [
            'Photos principales' => 'photos-principales',
            'Salle' => 'salle',
            'Bar' => 'bar',
            'Cuisine' => 'cuisine',
        ];
    }
}
