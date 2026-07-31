<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\Activity;

class LanguageChoices
{
    /**
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        return [
            'Français' => 'francais',
            'Anglais' => 'anglais',
            'Espagnol' => 'espagnol',
            'Allemand' => 'allemand',
            'Portuguais' => 'portuguais',
            'Néerlandais' => 'neerlandais',
        ];
    }
}
