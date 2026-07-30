<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\Place;

class EventChoices
{
    /**
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        return [
            'Lancement de produit' => 'product_launch',
            'Lancement de véhicule' => 'vehicle_launch',
            'Road show' => 'road_show',
            'Convention' => 'convention',
            'Séminaire' => 'seminar',
            'Formation' => 'training',
            'Team building' => 'team_building',
            'Comité de direction' => 'management_committee',
            'After Work' => 'after_work',
            'Colloque' => 'colloquium',
            'Conférences et congrés' => 'conferences_and_congresses',
            'Salons et expositions' => 'trade_shows_and_exhibitions',
            'Simposium' => 'symposium',
            'Évènement hybride' => 'hybrid_event',
        ];
    }
}
