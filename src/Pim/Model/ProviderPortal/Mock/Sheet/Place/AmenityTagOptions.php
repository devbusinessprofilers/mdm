<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\Place;

use App\Pim\Model\ProviderPortal\Form\Tag\TagOption;

class AmenityTagOptions
{
    /**
     * @return array<TagOption>
     */
    public static function getTagOptions(): array
    {
        return [
            new TagOption('voiturier', 'Voiturier', 'car'),
            new TagOption('navette-gratuite', 'Navette  Gratuite', 'van'),
            new TagOption('vestiaire-bagagerie', 'Vestiaire / bagagerie', 'suitcase'),
            new TagOption('service-pressing', 'Service pressing', 'coat-hanger'),
            new TagOption('reception-24-24', 'Réception 24/24', 'call-bell'),
            new TagOption('photocopie', 'Photocopie', 'printer'),
            new TagOption('borne-de-recharge-pour-vehicules-electriques', 'Borne de recharge pour véhicules électriques', 'charging-battery'),
            new TagOption('room-service', 'Room service', 'room-service'),
        ];
    }
}
