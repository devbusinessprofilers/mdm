<?php

namespace App\Pim\Model\ProviderPortal\DTO\Localisation;

class ComputeRouteDTO
{
    public ?int $duration = null;

    public ?int $distance = null;

    public function setDuration(?int $duration): static
    {
        $this->duration = $duration;

        return $this;
    }

    public function setDistance(?int $distance): static
    {
        $this->distance = $distance;

        return $this;
    }
}
