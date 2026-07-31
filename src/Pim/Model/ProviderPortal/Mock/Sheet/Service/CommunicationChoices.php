<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\Service;

class CommunicationChoices
{
    /**
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        return [
            'Agence de communication' => 'agence-de-communication',
            'Graphistes' => 'graphistes',
            'PLV / Signalétique' => 'plv-signaletique',
            'Fournitures pour expositions' => 'fournitures-pour-expositions',
            'Standistes' => 'standistes',
            'Site web événementiels' => 'site-web-evenementiels',
            'Imprimeur' => 'imprimeur',
        ];
    }
}
