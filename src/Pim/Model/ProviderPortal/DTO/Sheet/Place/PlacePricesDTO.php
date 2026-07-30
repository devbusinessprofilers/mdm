<?php

namespace App\Pim\Model\ProviderPortal\DTO\Sheet\Place;

use App\Pim\Model\ProviderPortal\DTO\CateringFormulaDTO;
use App\Pim\Model\ProviderPortal\DTO\OptionalPriceDTO;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Place\CateringFormulaContentChoices;

class PlacePricesDTO
{
    // One day seminar
    public ?OptionalPriceDTO $halfDayStudySeminar = null;
    public ?OptionalPriceDTO $oneDayStudySeminar = null;
    public ?OptionalPriceDTO $halfDayStudySeminarWithCocktail = null;
    public ?OptionalPriceDTO $oneDayStudySeminarWithCocktail = null;

    // Seminar with overnight stay
    public ?OptionalPriceDTO $semiResidentSeminar = null;
    public ?OptionalPriceDTO $residentSeminar = null;
    public ?OptionalPriceDTO $allInclusiveSeminar = null;

    // Room-only location
    public ?OptionalPriceDTO $halfDayLocation = null;
    public ?OptionalPriceDTO $oneDayLocation = null;
    public ?OptionalPriceDTO $eveningLocation = null;

    // Cocktail and evening parties
    public ?OptionalPriceDTO $brunchCocktail = null;
    public ?OptionalPriceDTO $cocktailParty = null;
    public ?OptionalPriceDTO $danceParty = null;
    public ?OptionalPriceDTO $dinnerParty = null;

    // Catering
    /**
     * @var array<CateringFormulaDTO>
     */
    public array $cateringFormulas = [];
    public ?OptionalPriceDTO $sitDownLunch = null;
    public ?OptionalPriceDTO $sitDownDinner = null;
    public ?OptionalPriceDTO $wineOption = null;
    public ?OptionalPriceDTO $alcoholOption = null;

    // Group accommodation
    public ?OptionalPriceDTO $groupSingleRoom = null;
    public ?OptionalPriceDTO $groupSitDownDiner = null;
    public ?OptionalPriceDTO $groupTwinRoom = null;
    public ?OptionalPriceDTO $groupDoubleRoom = null;

    public static function mock(): self
    {
        $data = new self();

        // One day seminar
        $data->halfDayStudySeminar = (new OptionalPriceDTO(true))->setPrice(361.82);
        $data->oneDayStudySeminar = new OptionalPriceDTO();
        $data->halfDayStudySeminarWithCocktail = new OptionalPriceDTO();
        $data->oneDayStudySeminarWithCocktail = (new OptionalPriceDTO(true))->setPrice(361.82);

        // Seminar with overnight stay
        $data->semiResidentSeminar = (new OptionalPriceDTO(true))->setPrice(361.82);
        $data->residentSeminar = (new OptionalPriceDTO(true))->setPrice(361.82);
        $data->allInclusiveSeminar = (new OptionalPriceDTO(true))->setPrice(361.82);

        // Room-only location
        $data->halfDayLocation = (new OptionalPriceDTO(true))->setPrice(361.82);
        $data->oneDayLocation = new OptionalPriceDTO();
        $data->eveningLocation = new OptionalPriceDTO();

        // Cocktail and evening parties
        $data->brunchCocktail = (new OptionalPriceDTO(true))->setPrice(361.82);
        $data->cocktailParty = new OptionalPriceDTO();
        $data->danceParty = new OptionalPriceDTO();
        $data->dinnerParty = (new OptionalPriceDTO(true))->setPrice(361.82);

        // Catering
        $data->cateringFormulas = [
            (new CateringFormulaDTO())
                ->setName('Formula 1')
                ->setMinimumParticipant(10)
                ->setMinimumPrice(361.82)
                ->addCateringFormulaContent(array_rand(array_flip(CateringFormulaContentChoices::getChoices())))
                ->addCateringFormulaContent(array_rand(array_flip(CateringFormulaContentChoices::getChoices())))
                ->addCateringFormulaContent(array_rand(array_flip(CateringFormulaContentChoices::getChoices()))),
        ];
        $data->sitDownLunch = (new OptionalPriceDTO(true))->setPrice(361.82);
        $data->sitDownDinner = new OptionalPriceDTO();
        $data->wineOption = (new OptionalPriceDTO(true))->setPrice(361.82);
        $data->alcoholOption = new OptionalPriceDTO();

        // Group accommodation
        $data->groupSingleRoom = (new OptionalPriceDTO(true))->setPrice(361.82);
        $data->groupSitDownDiner = (new OptionalPriceDTO(true))->setPrice(361.82);
        $data->groupTwinRoom = new OptionalPriceDTO();
        $data->groupDoubleRoom = new OptionalPriceDTO();

        return $data;
    }
}
