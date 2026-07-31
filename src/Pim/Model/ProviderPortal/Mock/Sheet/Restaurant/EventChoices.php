<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\Restaurant;

class EventChoices
{
    /**
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        return [
            'Petit déjeuner' => 'petit-dejeuner',
            'Brunch' => 'brunch',
            'Déjeuner assis' => 'dejeuner-assis',
            'Diner assis' => 'diner-assis',
            'Dîner de gala' => 'diner-de-gala',
            'Banquet (grand repas assis)' => 'banquet-grand-repas-assis',
            'Cocktail déjeunatoire' => 'cocktail-dejeunatoire',
            'Cocktail dînatoire' => 'cocktail-dinatoire',
            'Afterwork / Soirée festive' => 'afterwork-soiree-festive',
        ];
    }
}
