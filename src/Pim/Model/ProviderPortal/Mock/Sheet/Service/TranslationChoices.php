<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\Service;

class TranslationChoices
{
    /**
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        return [
            'Interprètes de conférence' => 'interpretes-de-conference',
            'Traducteurs simultanés' => 'traducteurs-simultanes',
            'Fourniture et gestion de matériel de traduction' => 'fourniture-et-gestion-de-materiel-de-traduction',
        ];
    }
}
