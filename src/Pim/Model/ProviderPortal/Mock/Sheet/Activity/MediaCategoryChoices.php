<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\Activity;

class MediaCategoryChoices
{
    /**
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        return [
            'Photos principales' => 'photos-principales',
            'Activités intérieures' => 'activites-interieures',
            'Activités extérieure' => 'activites-exterieure',
        ];
    }
}
