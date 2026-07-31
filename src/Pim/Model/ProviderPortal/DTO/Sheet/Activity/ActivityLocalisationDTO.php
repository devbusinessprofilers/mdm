<?php

namespace App\Pim\Model\ProviderPortal\DTO\Sheet\Activity;

use App\Pim\Enum\ProviderPortal\GeographicalRangeEnum;
use App\Pim\Model\ProviderPortal\DTO\Localisation\AddressDTO;

class ActivityLocalisationDTO
{
    public AddressDTO $address;

    public ?GeographicalRangeEnum $geographicRange = null;

    /**
     * @var array<string>
     */
    public ?array $countries = null;

    /**
     * @var array<string>
     */
    public ?array $departments = null;

    public function __construct()
    {
        $this->address = new AddressDTO();
    }

    public static function mock(): self
    {
        $data = new self();

        $data->geographicRange = GeographicalRangeEnum::FIX_ADDRESS;

        return $data;
    }
}
