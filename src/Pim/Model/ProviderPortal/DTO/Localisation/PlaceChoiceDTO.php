<?php

namespace App\Pim\Model\ProviderPortal\DTO\Localisation;

class PlaceChoiceDTO
{
    public ?string $label = null;

    public function __construct(
        public string $value,
    ) {
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function __toString(): string
    {
        return json_encode(['label' => $this->label, 'value' => $this->value]) ?? '';
    }
}
