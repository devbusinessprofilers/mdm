<?php

namespace App\Pim\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class Tooltip
{
    public string $icon = 'info-circle';

    public string $placement = 'right';
}
