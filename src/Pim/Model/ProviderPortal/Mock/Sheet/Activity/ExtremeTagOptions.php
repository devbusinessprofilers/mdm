<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\Activity;

use App\Pim\Model\ProviderPortal\Form\Tag\TagOption;

class ExtremeTagOptions
{
    /**
     * @return array<TagOption>
     */
    public static function getTagOptions(): array
    {
        return [
            new TagOption('karting', 'Karting'),
            new TagOption('rallye', 'Rallye'),
            new TagOption('sports-mecaniques-quad-buggy-etc', 'Sports mécaniques (quad, buggy, etc.)'),
            new TagOption('activites-aeriennes-parapente-montgolfiere-helico', 'Activités aériennes (parapente, montgolfière, hélico…)'),
        ];
    }
}
