<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\Restaurant;

class CateringChoices
{
    /**
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        return [
            'Déjeuner assis' => 'dejeuner-assis',
            'Cocktail déjeunatoire 10 pers' => 'cocktail-dejeunatoire-10-pers',
            'Diner assis' => 'diner-assis',
            'Cocktail dînatoire 10 pers' => 'cocktail-dinatoire-10-pers',
            'Forfait vin' => 'forfait-vin',
            'Forfait alcool (apériif digesitf)' => 'forfait-alcool-aperiif-digesitf',
        ];
    }
}
