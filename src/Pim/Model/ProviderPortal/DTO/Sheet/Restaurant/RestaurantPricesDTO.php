<?php

namespace App\Pim\Model\ProviderPortal\DTO\Sheet\Restaurant;

use App\Pim\Model\ProviderPortal\DTO\OptionalPriceDTO;

class RestaurantPricesDTO
{
    public ?OptionalPriceDTO $seatedLunch = null;

    public ?OptionalPriceDTO $cocktailLunchParty = null;

    public ?OptionalPriceDTO $seatedDinner = null;

    public ?OptionalPriceDTO $cocktailDinnerParty = null;

    public ?OptionalPriceDTO $wineOption = null;

    public ?OptionalPriceDTO $alcoholOption = null;

    public static function mock(): self
    {
        $data = new self();

        $data->seatedLunch = (new OptionalPriceDTO(true))->setPrice(361.82);
        $data->cocktailLunchParty = new OptionalPriceDTO();
        $data->seatedDinner = new OptionalPriceDTO();
        $data->cocktailDinnerParty = (new OptionalPriceDTO(true))->setPrice(361.82);
        $data->wineOption = (new OptionalPriceDTO(true))->setPrice(361.82);
        $data->alcoholOption = new OptionalPriceDTO();

        return $data;
    }
}
