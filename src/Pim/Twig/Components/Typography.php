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
        $this->variant = TypographyVariantEnum::tryFrom($variant) ?? TypographyVariantEnum::BODY_MEDIUM;
        $this->textColor = TypographyTextColorEnum::tryFrom($textColor) ?? TypographyTextColorEnum::DARK;
    }
}
