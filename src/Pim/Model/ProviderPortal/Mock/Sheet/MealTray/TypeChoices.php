<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\MealTray;

class TypeChoices
{
    /**
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        return [
            'Plateau-repas (entrée, plat, dessert)' => 'plateau-repas-entree-plat-dessert',
            'Sélection à partager' => 'selection-a-partager',
            'Corbeille de fruits' => 'corbeille-de-fruits',
            'Crêpes' => 'crepes',
            'Galette des rois' => 'galette-des-rois',
            'Chocolats' => 'chocolats',
            'Boisson' => 'boisson',
        ];
    }
}
