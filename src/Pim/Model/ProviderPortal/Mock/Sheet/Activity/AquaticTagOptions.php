<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\Activity;

use App\Pim\Model\ProviderPortal\Form\Tag\TagOption;

class AquaticTagOptions
{
    /**
     * @return array<TagOption>
     */
    public static function getTagOptions(): array
    {
        return [
            new TagOption('canoe-kayak', 'Canoë / Kayak'),
            new TagOption('voile', 'Voile'),
            new TagOption('paddle', 'Paddle'),
            new TagOption('plongee-snorkeling', 'Plongée / Snorkeling'),
            new TagOption('croisieres', 'Croisières'),
        ];
    }
}
