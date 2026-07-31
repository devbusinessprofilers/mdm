<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\Place;

class MobilityChoices
{
    /**
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        return [
            'Proche de transports en commun (si oui précisez lesquels)' => 'proche-de-transports-en-commun-si-oui-precisez-lesquels',
            'Mise à disposition de moyens de mobilité douce avec accessoires de sécurité associés (station d\'accueil de vélos et/ou de trotinettes)' => 'mise-a-disposition-de-moyens-de-mobilite-douce-avec-accessoires-de-securite-associes-station-d-accueil-de-velos-et-ou-de-trotinettes',
            'Offre d\'un pass de transports en commun valable pour la durée de l’événement' => 'offre-d-un-pass-de-transports-en-commun-valable-pour-la-duree-de-l-evenement',
            'Utilisation de véhicules à faibles émissions de gaz à effets de serre (électriques, hybrides, …)' => 'utilisation-de-vehicules-a-faibles-emissions-de-gaz-a-effets-de-serre-electriques-hybrides',
            'Accès PMR' => 'acces-pmr',
        ];
    }
}
