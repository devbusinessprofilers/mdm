<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\Service;

class MediaCategoryChoices
{
    /**
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        return [
            'Photos principales' => 'photos-principales',
            'Matériel' => 'materiel',
            'Divers' => 'divers',
        ];
    }
}
