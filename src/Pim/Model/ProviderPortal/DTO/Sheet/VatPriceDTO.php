<?php

namespace App\Pim\Model\ProviderPortal\DTO\Sheet;

use App\Pim\Enum\ProviderPortal\VatEnum;

/**
 * Common DTO for price with VAT.
 */
class VatPriceDTO
{
    protected ?float $exTaxAmount = null;

    protected ?float $taxAmount = null;

    protected ?VatEnum $vat = null;

    public static function mock(): self
    {
        $data = new self();

        $data->exTaxAmount = 150;
        $data->vat = VatEnum::TVA_5_5;
        $data->taxAmount = $data->vat->calculateVat($data->exTaxAmount);

        return $data;
    }

    public function getExTaxAmount(): ?float
    {
        return $this->exTaxAmount;
    }

    public function setExTaxAmount(?float $exTaxAmount): void
    {
        $this->exTaxAmount = $exTaxAmount;
        $this->resolveTaxAmount();
    }

    public function getTaxAmount(): ?float
    {
        return $this->taxAmount;
    }

    public function resolveTaxAmount(): ?float
    {
        $this->taxAmount = (null === $this->vat || null === $this->exTaxAmount) ? 0 : $this->vat->calculateVat($this->exTaxAmount);

        return $this->taxAmount;
    }

    public function getVat(): ?VatEnum
    {
        return $this->vat;
    }

    public function setVat(?VatEnum $vat): void
    {
        $this->vat = $vat;
        $this->resolveTaxAmount();
    }
}
