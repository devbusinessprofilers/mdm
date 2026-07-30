<?php

namespace App\Pim\Model\ProviderPortal\Mock\Collaborator;

class SheetTypeChoices
{
    /**
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        return [
            'Activité' => 'activite',
            'Lieu' => 'lieu',
            'Plateau-repas' => 'plateau-repas',
            'Restaurant' => 'restaurant',
            'Service' => 'service',
        ];
    }
}
