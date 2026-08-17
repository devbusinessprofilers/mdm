<?php

namespace App\Pim\Twig\Components;

use App\Pim\Enum\ProviderPortal\Twig\Component\Tag\TagSizeEnum;
use App\Pim\Enum\ProviderPortal\Twig\Component\Tag\TagVariantEnum;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class Tag
{
    public string $label = '';

    public ?string $icon = null;

    public TagVariantEnum $variant;

    public TagSizeEnum $size;

    public function mount(?string $variant = null, ?string $size = null): void
    {
        // PHP 8.5 déprécie tryFrom(null) : garde explicite.
        $this->variant = (null !== $variant ? TagVariantEnum::tryFrom($variant) : null) ?? TagVariantEnum::PRIMARY;
        $this->size = (null !== $size ? TagSizeEnum::tryFrom($size) : null) ?? TagSizeEnum::SMALL;
    }
}
