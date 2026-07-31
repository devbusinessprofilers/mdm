<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\Place;

class SocialImpactChoices
{
    /**
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        return [
            'Travail avec des établissement ESAT / STPA' => 'travail-avec-des-etablissement-esat-stpa',
            'Politique de diversité et d\'inclusion envers les employés' => 'politique-de-diversite-et-d-inclusion-envers-les-employes',
            'Emploi de travailleurs en situation de handicap (si oui précisez le %)' => 'emploi-de-travailleurs-en-situation-de-handicap-si-oui-precisez-le',
        ];
    }
}
