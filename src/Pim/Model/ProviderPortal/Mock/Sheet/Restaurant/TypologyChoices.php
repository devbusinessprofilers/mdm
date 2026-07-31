<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\Restaurant;

class TypologyChoices
{
    /**
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        return [
            'Bar à vin' => 'bar-a-vin',
            'Bistronomique' => 'bistronomique',
            'Brasserie / Bistro' => 'brasserie-bistro',
            'Cabaret' => 'cabaret',
            'Café / Bar' => 'cafe-bar',
            'Etoilé' => 'etoile',
            'Gastronomique' => 'gastronomique',
            'Au bord de l\'eau' => 'au-bord-de-l-eau',
            'Au vert' => 'au-vert',
            'Bord de mer' => 'bord-de-mer',
            'Branché' => 'branche',
            'Buffet à volonté' => 'buffet-a-volonte',
            'Cosy' => 'cosy',
            'Cuisine contemporaine' => 'cuisine-contemporaine',
            'Estaminet' => 'estaminet',
            'Festif' => 'festif',
            'Immersif' => 'immersif',
            'Restaurant d\'altitude' => 'restaurant-d-altitude',
            'Restaurant d\'hôtel' => 'restaurant-d-hotel',
            'Sur une péniche' => 'sur-une-peniche',
            'Avec terrasse' => 'avec-terrasse',
            'Trattoria' => 'trattoria',
            'Avec vue exceptionnelle' => 'avec-vue-exceptionnelle',
            'Diner spectacle' => 'diner-spectacle',
            'Insolite' => 'insolite',
            'Rooftop / Vue panoramique' => 'rooftop-vue-panoramique',
        ];
    }
}
