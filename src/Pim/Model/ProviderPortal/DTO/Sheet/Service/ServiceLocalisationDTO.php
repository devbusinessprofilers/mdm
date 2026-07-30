<?php

namespace App\Pim\Model\ProviderPortal\DTO\Sheet\Service;

use App\Pim\Model\ProviderPortal\DTO\Localisation\AddressDTO;
use App\Pim\Model\ProviderPortal\DTO\Localisation\NearPlacesDTO;

class ServiceLocalisationDTO
{
    public AddressDTO $address;

    /** @var string[] */
    public array $geographicRange = [];

    public ?NearPlacesDTO $nearCities = null;
    public ?NearPlacesDTO $nearParkings = null;
    public ?NearPlacesDTO $nearTrainStations = null;
    public ?NearPlacesDTO $nearAirports = null;

    public bool $hasReducedMobilityAccess = false;
    public ?bool $isReducedMobilityFriendly = null;

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
            ->setDepartment('Paris')
            ->setArea('Île-de-France')
        ;
        $data->hasReducedMobilityAccess = true;
        $data->isReducedMobilityFriendly = true;

        return $data;
    }
}
