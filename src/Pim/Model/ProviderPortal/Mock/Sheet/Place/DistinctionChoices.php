<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\Place;

class DistinctionChoices
{
    /**
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        return [
            'Batiment HQE, NF Habitat, ou BBCA' => 'batiment-hqe-nf-habitat-ou-bbca',
            'Clef verte' => 'clef-verte',
            'Certification Green Food' => 'certification-green-food',
            'Certification Bon pour le Climat' => 'certification-bon-pour-le-climat',
            'Etoile Verte Michelin' => 'etoile-verte-michelin',
            'EcoVadis' => 'ecovadis',
            'Bureau Veritas' => 'bureau-veritas',
            'ISO 26000' => 'iso-26000',
            'ISO 14001' => 'iso-14001',
            'Autre' => 'autre',
        ];
    }
}
