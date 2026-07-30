<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\Activity;

use App\Pim\Model\ProviderPortal\Form\Tag\TagOption;

class CulinaryTagOptions
{
    /**
     * @return array<TagOption>
     */
    public static function getTagOptions(): array
    {
        return [
            new TagOption('oenologie-degustation-de-vins', 'Œnologie / Dégustation de vins'),
            new TagOption('atelier-mixologie-cocktails', 'Atelier mixologie / Cocktails'),
            new TagOption('atelier-cuisine-gastronomie', 'Atelier cuisine / Gastronomie'),
            new TagOption('atelier-patisserie', 'Atelier pâtisserie'),
            new TagOption('atelier-chocolat', 'Atelier chocolat'),
            new TagOption('food-tour', 'Food tour'),
        ];
    }
}
