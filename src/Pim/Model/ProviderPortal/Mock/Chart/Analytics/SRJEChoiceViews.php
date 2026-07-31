<?php

namespace App\Pim\Model\ProviderPortal\Mock\Chart\Analytics;

use Symfony\Component\Form\ChoiceList\View\ChoiceView;

class SRJEChoiceViews
{
    /**
     * @return array<string, string>
     */
    public static function getChoiceViews(): array
    {
        return [
            new ChoiceView('sr-je-1', 'sr-je-1', 'SR/JE 1'),
            new ChoiceView('sr-je-2', 'sr-je-2', 'SR/JE 2'),
            new ChoiceView('sr-je-3', 'sr-je-3', 'SR/JE 3'),
        ];
    }
}
