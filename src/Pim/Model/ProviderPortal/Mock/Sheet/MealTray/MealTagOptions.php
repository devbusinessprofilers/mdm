<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\MealTray;

use App\Pim\Model\ProviderPortal\Form\Tag\TagOption;

class MealTagOptions
{
    /**
     * @return array<TagOption>
     */
    public static function getTagOptions(): array
    {
        return [
            new TagOption('petit-dejeuner', 'Petit-déjeuner'),
            new TagOption('repas', 'Repas'),
            new TagOption('pause-gourmande', 'Pause gourmande'),
            new TagOption('aperitif', 'Apéritif'),
        ];
    }
}
