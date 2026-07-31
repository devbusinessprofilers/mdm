<?php

namespace App\Pim\Model\ProviderPortal\DTO\Localisation;

class SuggestionDTO
{
    public ?string $label = null;

    public function __construct(
        public string $placeId,
    ) {
    }

    public function setLabel(?string $label): static
    {
        $this->label = $label;

        return $this;
    }
}
