<?php

namespace App\Pim\Model\ProviderPortal\DTO\Localisation;

class CoordinatesDTO
{
    public function __construct(
        public ?float $latitude = null,
        public ?float $longitude = null,
    ) {
    }

    public static function mock(): self
    {
        // Nodevo - 6 rue Victor Hugo
        return new self(49.184746876672605, 2.4270186091299566);
    }
}
