<?php

namespace App\Pim\Model\ProviderPortal\DTO\Localisation;

class NearbyPlaceDTO
{
    public ?string $displayName = null;

    public ?string $uri = null;

    public ?CoordinatesDTO $position = null;

    /** @var string[] */
    public ?array $types = null;

    public ?float $rating = null;

    public function __construct(
        public string $id,
    ) {
    }

    public function setDisplayName(?string $displayName): static
    {
        $this->displayName = $displayName;

        return $this;
    }

    public function setUri(?string $uri): static
    {
        $this->uri = $uri;

        return $this;
    }

    public function setPosition(?CoordinatesDTO $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function setTypes(?array $types): static
    {
        $this->types = $types;

        return $this;
    }

    public function setRating(?float $rating): static
    {
        $this->rating = $rating;

        return $this;
    }
}
