<?php

namespace App\Pim\Model\ProviderPortal\Mock\Chart\Analytics;

use Symfony\Component\Form\ChoiceList\View\ChoiceView;

class SizeGroupChoiceViews
{
    /**
     * @return array<string, string>
     */
    public static function getChoiceViews(): array
    {
        return [
            new ChoiceView('taille-de-groupe-1', 'taille-de-groupe-1', 'Taille de groupe 1'),
            new ChoiceView('taille-de-groupe-2', 'taille-de-groupe-2', 'Taille de groupe 2'),
            new ChoiceView('taille-de-groupe-3', 'taille-de-groupe-3', 'Taille de groupe 3'),
        ];
    }
}
