<?php

namespace App\Pim\Twig\Components;

use App\Pim\Enum\ProviderPortal\Twig\Component\Tag\TagSizeEnum;
use App\Pim\Enum\ProviderPortal\Twig\Component\Tag\TagVariantEnum;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class StateTag
{
    public string $label = '';

    public ?string $icon = null;

    public bool $enabled = false;

    public TagVariantEnum $disableVariant;

    public TagVariantEnum $enableVariant;

    public TagSizeEnum $size;

    public function mount(?string $disableVariant = null, ?string $enableVariant = null, ?string $size = null): void
    {
        // PHP 8.5 déprécie tryFrom(null) : garde explicite.
        $this->disableVariant = (null !== $disableVariant ? TagVariantEnum::tryFrom($disableVariant) : null) ?? TagVariantEnum::NEUTRAL;
        $this->enableVariant = (null !== $enableVariant ? TagVariantEnum::tryFrom($enableVariant) : null) ?? TagVariantEnum::SUCCESS_PASTEL;
        $this->size = (null !== $size ? TagSizeEnum::tryFrom($size) : null) ?? TagSizeEnum::SMALL;
    }
}
