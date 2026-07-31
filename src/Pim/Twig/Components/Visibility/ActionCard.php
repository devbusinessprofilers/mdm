<?php

namespace App\Pim\Twig\Components\Visibility;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class ActionCard
{
    public string $label;
    public string $icon;
    public ?string $tip = null;
    public string $buttonLink;

    public function mount(?string $action = null): void
    {
        $this->buttonLink = $action ?? '#';
    }
}
