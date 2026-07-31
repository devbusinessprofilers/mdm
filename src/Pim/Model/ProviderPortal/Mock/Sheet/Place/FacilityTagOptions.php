<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\Place;

use App\Pim\Model\ProviderPortal\Form\Tag\TagOption;

class FacilityTagOptions
{
    /**
     * @return array<TagOption>
     */
    public static function getTagOptions(): array
    {
        return [
            new TagOption('amphitheatre', 'Amphithéatre', 'amphitheatre'),
            new TagOption('fumoir', 'Fumoir', 'cigarette'),
            new TagOption('jardin-parc', 'Jardin / Parc', 'tree'),
            new TagOption('rooftop', 'Rooftop', 'rooftop'),
            new TagOption('terrasse-cour-interieure', 'Terrasse / Cour intérieure', 'terrace'),
            new TagOption('balcon', 'Balcon', 'balcony'),
            new TagOption('cave-de-degustation', 'Cave de dégustation', 'bottle'),
            new TagOption('plage-privee', 'Plage privée', 'beach'),
            new TagOption('espace-de-coworking', 'Espace de coworking', 'coworking'),
            new TagOption('parking', 'Parking', 'parking'),
            new TagOption('business-center', 'Business Center', 'business-center'),
            new TagOption('ascenseur', 'Ascenseur', 'elevator'),
        ];
    }
}
