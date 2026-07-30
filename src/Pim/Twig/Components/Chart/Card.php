<?php

namespace App\Pim\Twig\Components\Chart;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class Card
{
    public string $label;

    public string $value;

    public string $variation;

    public bool $critical = false;
}
