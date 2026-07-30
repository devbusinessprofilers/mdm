<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\Activity;

use App\Pim\Model\ProviderPortal\Form\Tag\TagOption;

class WellnessTagOptions
{
    /**
     * @return array<TagOption>
     */
    public static function getTagOptions(): array
    {
        return [
            new TagOption('yoga-meditation', 'Yoga / méditation'),
            new TagOption('relaxation-sophrologie', 'Relaxation & sophrologie'),
            new TagOption('massage-soins', 'Massage & soins'),
            new TagOption('coaching-developpement-personnel', 'Coaching & développement personnel'),
        ];
    }
}
