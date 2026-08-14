<?php

namespace App\Pim\Enum\ProviderPortal\Twig\Component\Button;

use App\Pim\Enum\ProviderPortal\Twig\Component\Typography\TypographyTextColorEnum;

enum ButtonVariantEnum: string
{
    case PRIMARY = 'primary';
    case OUTLINE = 'outline';
    case TEXT = 'text';
    case NAVIGATION = 'navigation';

    public function getDefaultTextColor(): TypographyTextColorEnum
    {
        return match ($this) {
            self::PRIMARY => TypographyTextColorEnum::LIGHT,
            default => TypographyTextColorEnum::CONTROLLED,
        };
    }

    public function getDefaultIconColor(): TypographyTextColorEnum
    {
        return match ($this) {
            default => $this->getDefaultTextColor(),
        };
    }
}
