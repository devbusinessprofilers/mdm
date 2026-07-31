<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\Place;

class AtmosphereChoices
{
    /**
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        return [
            'Atypique / Insolite' => 'atypique-insolite',
            'Intimiste' => 'intimiste',
            'Cosy' => 'cosy',
            'Moderne / Design' => 'moderne-design',
            'Innovant / startup' => 'innovant-startup',
            'Industrielle' => 'industrielle',
            'Authentique / Historique' => 'authentique-historique',
            'Animé' => 'anime',
            'Vue imprenable' => 'vue-imprenable',
        ];
    }
}
