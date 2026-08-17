<?php

namespace App\Pim\Twig\Components;

use App\Pim\Enum\ProviderPortal\Twig\Component\Typography\TypographyTextColorEnum;
use App\Pim\Enum\ProviderPortal\Twig\Component\Typography\TypographyVariantEnum;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class Typography
{
    public string $element = 'span';

    public TypographyVariantEnum $variant;

    public TypographyTextColorEnum $textColor;

    public bool $bold = false;

    public bool $medium = false;

    public bool $center = false;

    public bool $underline = false;

    public bool $ellipsis = false;

    public function mount(?string $variant = null, ?string $textColor = null): void
    {
        // PHP 8.5 déprécie tryFrom(null) : garde explicite.
        $this->variant = (null !== $variant ? TypographyVariantEnum::tryFrom($variant) : null) ?? TypographyVariantEnum::BODY_MEDIUM;
        $this->textColor = (null !== $textColor ? TypographyTextColorEnum::tryFrom($textColor) : null) ?? TypographyTextColorEnum::DARK;
    }
}
