<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\MealTray;

use App\Pim\Model\ProviderPortal\Form\Tag\TagOption;

class DietaryPreferenceTagOptions
{
    /**
     * @return array<TagOption>
     */
    public static function getTagOptions(): array
    {
        return [
            new TagOption('halal', 'Halal'),
            new TagOption('vegetarien', 'Végétarien'),
            new TagOption('vegan', 'Vegan'),
            new TagOption('kasher', 'Kasher'),
        ];
    }
}
