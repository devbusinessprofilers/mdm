<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\Service;

class FoodChoices
{
    /**
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        return [
            'Foodtrucks' => 'foodtrucks',
            'Traiteurs' => 'traiteurs',
        ];
    }
}
