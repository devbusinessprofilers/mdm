<?php

namespace App\Pim\Model\ProviderPortal\DTO;

class CateringFormulaDTO
{
    public ?string $name = null;
    public ?int $minimumParticipant = null;
    public ?float $minimumPrice = null;
    /** @var string[] */
    public array $cateringFormulaContents = [];

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function setMinimumParticipant(?int $minimumParticipant): static
    {
        $this->minimumParticipant = $minimumParticipant;

        return $this;
    }

    public function setMinimumPrice(?float $minimumPrice): static
    {
        $this->minimumPrice = $minimumPrice;

        return $this;
    }

    public function addCateringFormulaContent(string $cateringFormulaContent): static
    {
        $this->cateringFormulaContents[] = $cateringFormulaContent;

        return $this;
    }
}
