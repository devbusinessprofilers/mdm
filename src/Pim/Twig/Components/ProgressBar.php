<?php

namespace App\Pim\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class ProgressBar
{
    public int $value = 0;

    public int $max = 100;

    public bool $large = false;

    public bool $noGradient = false;
}
