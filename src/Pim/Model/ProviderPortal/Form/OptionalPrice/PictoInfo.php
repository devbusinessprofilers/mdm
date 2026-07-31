<?php

namespace App\Pim\Model\ProviderPortal\Form\OptionalPrice;

class PictoInfo
{
    public ?string $transformer = null;

    public function __construct(
        public string $icon,
        public string $label,
    ) {
    }

    public function setTransformer(string $transformer): static
    {
        $this->transformer = $transformer;

        return $this;
    }
}
