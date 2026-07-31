<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\MealTray;

use App\Pim\Model\ProviderPortal\Form\Tag\TagOption;

class AllergenTagOptions
{
    /**
     * @return array<TagOption>
     */
    public static function getTagOptions(): array
    {
        return [
            new TagOption('gluten', 'Gluten'),
            new TagOption('sesame', 'Sésame'),
            new TagOption('fruits à coque', 'Fruits à coque'),
            new TagOption('crustaces', 'Crustacés'),
            new TagOption('oeuf', 'Œuf'),
            new TagOption('poisson', 'Poisson'),
            new TagOption('moutarde', 'Moutarde'),
            new TagOption('lait', 'Lait'),
            new TagOption('celeri', 'Céleri'),
            new TagOption('arachide', 'Arachide'),
            new TagOption('soja', 'Soja'),
            new TagOption('molusque', 'Molusque'),
            new TagOption('lupin', 'Lupin'),
            new TagOption('sulfite', 'Sulfite'),
        ];
    }
}
