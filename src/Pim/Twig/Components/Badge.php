<?php

namespace App\Pim\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class Badge
{
    public ?string $icon = 'user';

    public ?string $pictureUrl = null;

    public ?string $pictureText = null;
}
