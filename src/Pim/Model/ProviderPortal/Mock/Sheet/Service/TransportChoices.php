<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\Service;

class TransportChoices
{
    /**
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        return [
            'Transferts aéroport / gare' => 'transferts-aeroport-gare',
            'Navettes' => 'navettes',
            'Bus & minibus' => 'bus-minibus',
            'Transport VIP' => 'transport-vip',
            'Logistique technique' => 'logistique-technique',
            'Taxis / VTC' => 'taxis-vtc',
        ];
    }
}
