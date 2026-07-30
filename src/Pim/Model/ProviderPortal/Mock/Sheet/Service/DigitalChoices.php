<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\Service;

class DigitalChoices
{
    /**
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        return [
            'Sonorisation' => 'sonorisation',
            'Vidéo / Captation' => 'video-captation',
            'Éclairage & Lumière' => 'eclairage-lumiere',
            'Réalité virtuelle / digitale' => 'realite-virtuelle-digitale',
            'Vidéoconférence' => 'videoconference',
            'Location de matériel technique' => 'location-de-materiel-technique',
            'Effets spéciaux' => 'effets-speciaux',
        ];
    }
}
