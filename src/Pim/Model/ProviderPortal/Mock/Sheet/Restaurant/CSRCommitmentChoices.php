<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\Restaurant;

class CSRCommitmentChoices
{
    /**
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        return [
            'Produits locaux' => 'produits-locaux',
            'Restaurateur ESAT' => 'restaurateur-esat',
            'Produits bio' => 'produits-bio',
            'Produits frais non congelés' => 'produits-frais-non-congeles',
        ];
    }
}
