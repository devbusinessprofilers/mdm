<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\Service;

class FacilityChoices
{
    /**
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        return [
            'Décoration florale' => 'decoration-florale',
            'Décorateur / Scénographe' => 'decorateur-scenographe',
            'Mobilier événementiel' => 'mobilier-evenementiel',
            'Scénographie immersive' => 'scenographie-immersive',
            'Tentes & Chapiteaux' => 'tentes-chapiteaux',
            'Réalisation audiovisuelle' => 'realisation-audiovisuelle',
        ];
    }
}
