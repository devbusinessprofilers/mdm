<?php

namespace App\Pim\Twig\Components\Media;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class CropModal
{
    public string $identifier;

    public float $scaleStep;

    public float $minScale;

    public float $maxScale;
}
