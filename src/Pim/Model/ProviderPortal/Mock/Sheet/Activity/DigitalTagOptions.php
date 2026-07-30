<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\Activity;

use App\Pim\Model\ProviderPortal\Form\Tag\TagOption;

class DigitalTagOptions
{
    /**
     * @return array<TagOption>
     */
    public static function getTagOptions(): array
    {
        return [
            new TagOption('realite-virtuelle-vr', 'Réalité virtuelle / VR'),
            new TagOption('experiences-digitales-interactives', 'Expériences digitales interactives'),
            new TagOption('serious-games', 'Serious games'),
        ];
    }
}
