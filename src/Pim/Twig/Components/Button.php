<?php

namespace App\Pim\Twig\Components;

use App\Pim\Enum\ProviderPortal\Twig\Component\Button\ButtonIconSizeEnum;
use App\Pim\Enum\ProviderPortal\Twig\Component\Button\ButtonSizeEnum;
use App\Pim\Enum\ProviderPortal\Twig\Component\Button\ButtonVariantEnum;
use App\Pim\Enum\ProviderPortal\Twig\Component\Typography\TypographyTextColorEnum;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class Button
{
    public ButtonVariantEnum $variant;
    public ButtonSizeEnum $size;
    public TypographyTextColorEnum $textColor;
    public TypographyTextColorEnum $iconColor;
    public ButtonIconSizeEnum $iconSize;
    public bool $disabled = false;
    public bool $full = false;
    public bool $isActive = false;
    public bool $asLink = false;

    public ?string $icon = null;

    public function mount(
        ?string $variant = null,
        ?string $size = null,
        ?string $textColor = null,
        ?string $iconSize = null,
        ?string $iconColor = null,
    ): void {
        // PHP 8.5 déprécie tryFrom(null) : garde explicite.
        $this->variant = (null !== $variant ? ButtonVariantEnum::tryFrom($variant) : null) ?? ButtonVariantEnum::PRIMARY;
        $this->size = (null !== $size ? ButtonSizeEnum::tryFrom($size) : null) ?? ButtonSizeEnum::LARGE;
        $this->iconSize = (null !== $iconSize ? ButtonIconSizeEnum::tryFrom($iconSize) : null) ?? ButtonIconSizeEnum::MEDIUM;

        $this->initTextColor($textColor);
        $this->initIconColor($iconColor, $textColor);
    }

    protected function initTextColor(?string $textColor): void
    {
        $textColor = null !== $textColor ? TypographyTextColorEnum::tryFrom($textColor) : null;
        if (null !== $textColor) {
            $this->textColor = $textColor;

            return;
        }

        $this->textColor = $this->variant->getDefaultTextColor();
    }

    protected function initIconColor(?string $iconColor, ?string $textColor): void
    {
        $iconColor = null !== $iconColor ? TypographyTextColorEnum::tryFrom($iconColor) : null;
        if (null !== $iconColor) {
            $this->iconColor = $iconColor;

            return;
        }

        $textColor = null !== $textColor ? TypographyTextColorEnum::tryFrom($textColor) : null;
        if (null !== $textColor) {
            $this->iconColor = $textColor;

            return;
        }

        $this->iconColor = $this->variant->getDefaultIconColor();
    }
}
