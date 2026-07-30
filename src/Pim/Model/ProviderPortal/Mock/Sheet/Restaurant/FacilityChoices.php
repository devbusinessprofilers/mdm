<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\Restaurant;

class FacilityChoices
{
    /**
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        return [
            'Animation musicale / DJ' => 'animation-musicale-dj',
            'Ateliers culinaires' => 'ateliers-culinaires',
            'Décoration personnalisable' => 'decoration-personnalisable',
            'Allemand parlé' => 'allemand-parle',
            'Anglais parlé' => 'anglais-parle',
            'Climatisé' => 'climatise',
            'Karaoké' => 'karaoke',
            'Dégustation privée possible' => 'degustation-privee-possible',
            'Menus personnalisables' => 'menus-personnalisables',
            'Vestiaire' => 'vestiaire',
            'Espace bar / Lounge' => 'espace-bar-lounge',
            'Jardin / Patio privatif' => 'jardin-patio-privatif',
            'Terrasse extérieure' => 'terrasse-exterieure',
            'Café d’accueil' => 'cafe-d-accueil',
            'Room service' => 'room-service',
            'Barbecue' => 'barbecue',
            'Show cooking' => 'show-cooking',
        ];
    }
}
