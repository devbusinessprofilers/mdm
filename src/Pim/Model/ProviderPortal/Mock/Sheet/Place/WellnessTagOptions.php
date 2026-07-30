<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\Place;

use App\Pim\Model\ProviderPortal\Form\Tag\TagOption;

class WellnessTagOptions
{
    /**
     * @return array<TagOption>
     */
    public static function getTagOptions(): array
    {
        return [
            new TagOption('thalasso', 'Thalasso', 'thalasso'),
            new TagOption('piscine-interieure', 'Piscine intérieure', 'swimming'),
            new TagOption('piscine-exterieure', 'Piscine extérieure', 'swimming'),
            new TagOption('spa-et-centre-de-bien-etre', 'Spa et centre de bien-être', 'spa'),
            new TagOption('sauna', 'Sauna', 'sauna'),
            new TagOption('hammam', 'Hammam', 'hammam'),
            new TagOption('jaccuzi-bain-a-remous', 'Jaccuzi / Bain à remous', 'jaccuzi'),
            new TagOption('centre-de-remise-en-forme', 'Centre de remise en forme', 'bench'),
            new TagOption('fitness-salle-de-sport', 'Fitness / Salle de sport', 'dumbbell'),
            new TagOption('massage', 'Massage', 'massage'),
            new TagOption('espace-detente', 'Espace détente', 'hot-stone'),
        ];
    }
}
