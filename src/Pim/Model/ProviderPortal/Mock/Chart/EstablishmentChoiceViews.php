<?php

namespace App\Pim\Model\ProviderPortal\Mock\Chart;

use Symfony\Component\Form\ChoiceList\View\ChoiceView;

class EstablishmentChoiceViews
{
    /**
     * @return array<string, string>
     */
    public static function getChoiceViews(): array
    {
        return [
            new ChoiceView('etablissement-alpha', 'etablissement-alpha', 'Établissement Alpha'),
            new ChoiceView('etablissement-bravo', 'etablissement-bravo', 'Établissement Bravo'),
            new ChoiceView('etablissement-charlie', 'etablissement-charlie', 'Établissement Charlie'),
        ];
    }
}
