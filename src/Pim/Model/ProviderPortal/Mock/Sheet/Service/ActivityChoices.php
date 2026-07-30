<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\Service;

class ActivityChoices
{
    /**
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        return [
            'DJ' => 'dj',
            'Magicien' => 'magicien',
            'Mentaliste' => 'mentaliste',
            'Hypnotiseur' => 'hypnotiseur',
            'Animateur / Maître de cérémonie' => 'animateur-maitre-de-ceremonie',
            'Performeurs (danse, cirque, etc.)' => 'performeurs-danse-cirque-etc',
            'Spectacle clé en main' => 'spectacle-cle-en-main',
            'Photobooth' => 'photobooth',
            'Artistes' => 'artistes',
            'Photographe' => 'photographe',
            'Musicien' => 'musicien',
            'Intervenant' => 'intervenant',
            'Flair' => 'flair',
        ];
    }
}
