<?php

namespace App\Pim\Twig\Components\Modal;

use App\Pim\Twig\Components\Button;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class TriggerButton extends Button
{
    public string $modalIdentifier;
}
