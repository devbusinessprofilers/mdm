<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\Service;

class GiftChoices
{
    /**
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        return [
            'Objets personnalisés / Goodies' => 'objets-personnalises-goodies',
            'Coffrets cadeaux' => 'coffrets-cadeaux',
            'Artisanat local' => 'artisanat-local',
        ];
    }
}
