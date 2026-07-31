<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\Activity;

class ThematicChoices
{
    /**
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        return [
            'Sportives & Ludiques' => 'sportives-ludiques',
            'Sensations fortes & Sports mécaniques' => 'sensations-fortes-sports-mecaniques',
            'Nautiques & Aquatiques' => 'nautiques-aquatiques',
            'Culinaires & Œnologiques' => 'culinaires-oenologiques',
            'Créatives, Artistiques & Musicales' => 'creatives-artistiques-musicales',
            'Culturelles, Réflexions & Découvertes' => 'culturelles-reflexions-decouvertes',
            'Nature & RSE' => 'nature-rse',
            'Bien-être & Détente' => 'bien-etre-detente',
            'Numérique' => 'digital',
        ];
    }
}
