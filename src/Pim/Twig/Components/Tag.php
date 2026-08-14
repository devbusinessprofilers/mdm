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
        $this->variant = TagVariantEnum::tryFrom($variant) ?? TagVariantEnum::PRIMARY;
        $this->size = TagSizeEnum::tryFrom($size) ?? TagSizeEnum::SMALL;
    }
}
