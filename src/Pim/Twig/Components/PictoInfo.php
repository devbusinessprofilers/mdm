<?php

namespace App\Pim\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class PictoInfo
{
    public string $icon;

    public ?string $label;

    public ?string $detail = null;
}
