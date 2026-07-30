<?php

namespace App\Pim\Model\ProviderPortal\DTO\Sheet\Place;

use App\Pim\Model\ProviderPortal\DTO\Localisation\AddressDTO;
use App\Pim\Model\ProviderPortal\DTO\Localisation\CoordinatesDTO;
use App\Pim\Model\ProviderPortal\DTO\Localisation\NearPlacesDTO;
use App\Pim\Model\ProviderPortal\DTO\Localisation\PlaceChoiceDTO;

class PlaceLocalisationDTO
{
    public AddressDTO $address;

    public ?NearPlacesDTO $nearTrainStations = null;
    public ?NearPlacesDTO $nearSubwayStations = null;
    public ?NearPlacesDTO $nearAirports = null;
    public ?NearPlacesDTO $nearLightRailStations = null;
    public ?NearPlacesDTO $nearCities = null;

    public bool $hasReducedMobilityAccess = false;
    public ?string $reducedMobilityAccessDescription = null;

    public function __construct()
    {
        $this->address = new AddressDTO();
    }

    public static function mock(): self
    {
        $data = new self();

        $data->address = (new AddressDTO())
            ->setCountry('FR')
            ->setCity('Gouvieux')
            ->setZipCode('60270')
            ->setStreet('2 Av. François Mathet ')
            ->setDepartment('Oise')
            ->setArea('Hauts-de-France')
            ->setPosition(CoordinatesDTO::mock())
        ;

        $data->nearTrainStations = (new NearPlacesDTO())
            ->addPlaceChoice(new PlaceChoiceDTO('ChIJl2ChmG1I5kcRvSyEGUZwaJc'))
            ->addPlaceChoice(new PlaceChoiceDTO('ChIJfZZqe95I5kcRg8ubT1y6m5Y'))
            ->addPlaceChoice(new PlaceChoiceDTO('ChIJUVM7jz5P5kcRJvUND5FVeSI'))
        ;

        $data->hasReducedMobilityAccess = true;

        return $data;
    }
}
