<?php

namespace App\Pim\Model\ProviderPortal\DTO\Localisation;

class AddressDTO
{
    public ?CoordinatesDTO $position = null;

    public string $country = 'FR';

    public ?string $city = null;

    public ?string $zipCode = null;

    public ?string $street = null;

    public ?string $district = null;

    public ?string $department = null;

    public ?string $area = null;

    public function setPosition(?CoordinatesDTO $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function setCountry(?string $country): static
    {
        $this->country = $country;

        return $this;
    }

    public function setCity(?string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function setZipCode(?string $zipCode): static
    {
        $this->zipCode = $zipCode;

        return $this;
    }

    public function setStreet(?string $street): static
    {
        $this->street = $street;

        return $this;
    }

    public function setDistrict(?string $district): static
    {
        $this->district = $district;

        return $this;
    }

    public function setDepartment(?string $department): static
    {
        $this->department = $department;

        return $this;
    }

    public function setArea(?string $area): static
    {
        $this->area = $area;

        return $this;
    }
}
