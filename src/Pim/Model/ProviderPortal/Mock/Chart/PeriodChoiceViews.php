<?php

namespace App\Pim\Model\ProviderPortal\Mock\Chart;

use Symfony\Component\Form\ChoiceList\View\ChoiceView;

class PeriodChoiceViews
{
    /**
     * @return array<string, string>
     */
    public static function getChoiceViews(): array
    {
        return [
            new ChoiceView('periode-alpha', 'periode-alpha', 'Période Alpha'),
            new ChoiceView('periode-bravo', 'periode-bravo', 'Période Bravo'),
            new ChoiceView('periode-charlie', 'periode-charlie', 'Période Charlie'),
        ];
    }
}
