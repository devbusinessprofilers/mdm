<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\Activity;

use App\Pim\Model\ProviderPortal\Form\Tag\TagOption;

class NaturalTagOptions
{
    /**
     * @return array<TagOption>
     */
    public static function getTagOptions(): array
    {
        return [
            new TagOption('atelier-fresque-climat-biodiversite-fresque-vegetale', 'Atelier fresque (climat, biodiversité, fresque végétale)'),
            new TagOption('activites-au-vert-randonnee-balade-nature', 'Activités au vert (randonnée, balade nature)'),
            new TagOption('ateliers-eco-responsables-diy-recyclage-construction-collaborative', 'Ateliers éco-responsables (DIY, recyclage, construction collaborative)'),
        ];
    }
}
