<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\Service;

class ReceptionChoices
{
    /**
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        return [
            'Agents d’accueil' => 'agents-d-accueil',
            'Hôtesses' => 'hotesses',
            'Agents de sécurité / Vigiles' => 'agents-de-securite-vigiles',
            'Contrôle d’accès' => 'controle-d-acces',
        ];
    }
}
