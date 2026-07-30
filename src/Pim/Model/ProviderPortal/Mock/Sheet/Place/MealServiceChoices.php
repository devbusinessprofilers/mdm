<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\Place;

class MealServiceChoices
{
    /**
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        return [
            'Bar' => 'bar',
            'Collation' => 'collation',
            'Petit-déjeuner' => 'petit-dejeuner',
            'Déjeuner / Diner' => 'dejeuner-diner',
            'Déjeuner plateau repas' => 'dejeuner-plateau-repas',
            'Déjeuner assis buffet' => 'dejeuner-assis-buffet',
            'Déjeuner assis à l\'assiette' => 'dejeuner-assis-a-l-assiette',
            'Déjeuner cocktail dinatoire' => 'dejeuner-cocktail-dinatoire',
            'Pause et petit déjeuner' => 'pause-et-petit-dejeuner',
            'Restauration en Terrasse' => 'restauration-en-terrasse',
            'Restauration en Rooftop' => 'restauration-en-rooftop',
            'Restauration en Jardin / Cours' => 'restauration-en-jardin-cours',
            'Cave à vins / Espace dégustation' => 'cave-a-vins-espace-degustation',
            'Café d’accueil' => 'cafe-d-accueil',
            'Room service' => 'room-service',
            'Barbecue' => 'barbecue',
            'Show cooking' => 'show-cooking',
        ];
    }
}
