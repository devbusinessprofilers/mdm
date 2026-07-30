<?php

namespace App\Pim\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class Stars
{
    public int $value = 0;

    public int $max = 100;
}
