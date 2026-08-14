<?php

namespace App\Pim\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class Collapse
{
    public bool $isExpanded = false;
    public ?string $wrapperClass = null;
    public ?string $containerClass = null;
}
