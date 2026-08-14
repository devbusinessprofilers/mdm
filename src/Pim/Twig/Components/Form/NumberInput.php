<?php

namespace App\Pim\Twig\Components\Form;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class NumberInput
{
    public string $id;

    public string $name;

    public float $step = 1;

    public int $scale = 0;

    public ?float $minValue = null;

    public ?float $maxValue = null;

    public string $radix = ',';

    public string $thousandsSeparator = ' ';

    public bool $padFractionalZeros = false;

    public bool $disabled = false;

    public bool $readonly = false;
}
