<?php

namespace App\Pim\Enum\ProviderPortal;

enum VatEnum: string
{
    case TVA_0 = '0%';
    case TVA_5_5 = '5,5%';
    case TVA_10 = '10%';
    case TVA_20 = '20%';

    public function applyVat(float $amount): float
    {
        $taxIncludedAmount = $amount * (1 + $this->getVatRate());

        return round($taxIncludedAmount, 2, PHP_ROUND_HALF_DOWN);
    }

    public function calculateVat(float $amount): float
    {
        $vatAmount = $this->getVatRate() * $amount;

        return round($vatAmount, 2, PHP_ROUND_HALF_DOWN);
    }

    public function getVatRate(): float
    {
        return match ($this) {
            self::TVA_0 => 0,
            self::TVA_5_5 => 0.055,
            self::TVA_10 => 0.1,
            self::TVA_20 => 0.2,
        };
    }
}
