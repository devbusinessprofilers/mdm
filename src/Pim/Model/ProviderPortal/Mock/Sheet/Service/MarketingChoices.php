<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\Service;

class MarketingChoices
{
    /**
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        return [
            'Application événementielle' => 'application-evenementielle',
            'Site web événementiel' => 'site-web-evenementiel',
            'Incription et billeterie' => 'incription-et-billeterie',
            'Checking & badges' => 'checking-badges',
            'programmes en ligne' => 'programmes-en-ligne',
            'matchmaking' => 'matchmaking',
            'rendez-vous' => 'rendez-vous',
            'streaming événementiel' => 'streaming-evenementiel',
            'webinar' => 'webinar',
            'Création de contenus visuels instantanés' => 'creation-de-contenus-visuels-instantanes',
            'Création de visuels et supports événementiels' => 'creation-de-visuels-et-supports-evenementiels',
            'Animation interactive' => 'animation-interactive',
        ];
    }
}
