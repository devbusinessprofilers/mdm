<?php

namespace App\Pim\Model\ProviderPortal\DTO\Sheet\Restaurant;

use App\Pim\Model\ProviderPortal\DTO\Localisation\AddressDTO;
use App\Pim\Model\ProviderPortal\DTO\Localisation\CoordinatesDTO;
use App\Pim\Model\ProviderPortal\DTO\Localisation\NearPlacesDTO;

class RestaurantLocalisationDTO
{
    public AddressDTO $address;

    public ?NearPlacesDTO $nearTrainStations = null;
    public ?NearPlacesDTO $nearSubwayStations = null;
    public ?NearPlacesDTO $nearAirports = null;
    public ?NearPlacesDTO $nearLightRailStations = null;
    public ?NearPlacesDTO $nearCities = null;

    public bool $hasReducedMobilityAccess = false;
    public ?bool $hasReducedMobilityToilets = null;

    public function __construct()
    {
        $this->address = new AddressDTO();
    }

    public static function mock(): self
    {
        $data = new self();
        $data->address = (new AddressDTO())
            ->setCountry('FR')
            ->setCity('Paris')
            ->setZipCode('75015')
            ->setStreet('28 Bd Pasteur')
            ->setDepartment('Paris')
            ->setArea('Île-de-France')
            ->setPosition(CoordinatesDTO::mock())
        ;
        $data->hasReducedMobilityAccess = true;
        $data->hasReducedMobilityToilets = true;

        return $data;
    }
}
