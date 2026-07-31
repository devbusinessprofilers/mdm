<?php

namespace App\Pim\Model\ProviderPortal\DTO\Sheet\Place;

use App\Pim\Model\ProviderPortal\DTO\Localisation\CoordinatesDTO;

class PlaceDescriptionDTO
{
    public ?string $global = null;

    public ?string $asset1 = null;

    public ?string $asset2 = null;

    public ?string $asset3 = null;

    public ?string $asset4 = null;

    public ?string $asset5 = null;

    /**
     * @var array<string>
     */
    public array $significantPointIds = [];

    /**
     * @todo: coordinates depends on address defined in PlaceLocalisationDTO
     */
    public ?CoordinatesDTO $coordinates = null;

    public static function mock(): self
    {
        $data = new self();

        $data->global = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.';
        $data->asset1 = 'Volutpat';
        $data->asset2 = 'Lacinia';
        $data->asset3 = 'Pretium';
        $data->asset4 = 'Rhoncus';
        $data->asset5 = 'Pulvinar';
        $data->significantPointIds = [];
        $data->coordinates = CoordinatesDTO::mock();

        return $data;
    }
}
