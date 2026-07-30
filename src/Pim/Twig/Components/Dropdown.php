<?php

namespace App\Pim\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class Dropdown
{
    public bool $isFloating = true;

    public bool $isFixed = false;

    public bool $withIndicator = true;

    public ?string $wrapperClass = null;

    public ?string $containerClass = null;
}
