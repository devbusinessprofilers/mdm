<?php

namespace App\Pim\Model\ProviderPortal\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class OptionalPriceDTO
{
    #[Assert\NotBlank]
    public ?float $price = null;

    public function __construct(
        public bool $isOptionSelected = false,
    ) {
    }

    public function setPrice(string|float|null $price): static
    {
        if (is_string($price)) {
            $price = (float) $price;
        }

        $this->price = $price;

        return $this;
    }
}
