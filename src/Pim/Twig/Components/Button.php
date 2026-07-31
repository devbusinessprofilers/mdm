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
        $this->variant = ButtonVariantEnum::tryFrom($variant) ?? ButtonVariantEnum::PRIMARY;
        $this->size = ButtonSizeEnum::tryFrom($size) ?? ButtonSizeEnum::LARGE;
        $this->iconSize = ButtonIconSizeEnum::tryFrom($iconSize) ?? ButtonIconSizeEnum::MEDIUM;

        $this->initTextColor($textColor);
        $this->initIconColor($iconColor, $textColor);
    }

    protected function initTextColor(?string $textColor): void
    {
        $textColor = TypographyTextColorEnum::tryFrom($textColor);
        if (null !== $textColor) {
            $this->textColor = $textColor;

            return;
        }

        $this->textColor = $this->variant->getDefaultTextColor();
    }

    protected function initIconColor(?string $iconColor, ?string $textColor): void
    {
        $iconColor = TypographyTextColorEnum::tryFrom($iconColor);
        if (null !== $iconColor) {
            $this->iconColor = $iconColor;

            return;
        }

        $textColor = TypographyTextColorEnum::tryFrom($textColor);
        if (null !== $textColor) {
            $this->iconColor = $textColor;

            return;
        }

        $this->iconColor = $this->variant->getDefaultIconColor();
    }
}
