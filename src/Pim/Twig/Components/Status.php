<?php

namespace App\Pim\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class Status
{
    public bool $state = false;
}
