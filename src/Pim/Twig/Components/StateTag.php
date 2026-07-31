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
        $this->disableVariant = TagVariantEnum::tryFrom($disableVariant) ?? TagVariantEnum::NEUTRAL;
        $this->enableVariant = TagVariantEnum::tryFrom($enableVariant) ?? TagVariantEnum::SUCCESS_PASTEL;
        $this->size = TagSizeEnum::tryFrom($size) ?? TagSizeEnum::SMALL;
    }
}
