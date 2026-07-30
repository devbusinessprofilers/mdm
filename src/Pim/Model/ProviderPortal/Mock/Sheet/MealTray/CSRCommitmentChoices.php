<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\MealTray;

class CSRCommitmentChoices
{
    /**
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        return [
            'Produits locaux' => 'produits-locaux',
            'Emballage eco-friendly' => 'emballage-eco-friendly',
            'Traiteur ESAT' => 'traiteur-esat',
            'Produits bio' => 'produits-bio',
            'Livraison écologique' => 'livraison-ecologique',
            'Produits frais non congelés' => 'produits-frais-non-congeles',
        ];
    }
}
