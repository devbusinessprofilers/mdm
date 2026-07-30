<?php

namespace App\Pim\Enum\ProviderPortal\Twig\Component\Tag;

use App\Pim\Enum\ProviderPortal\Twig\Component\Typography\TypographyVariantEnum;

enum TagSizeEnum: string
{
    case LARGE = 'lg';
    case SMALL = 'sm';

    public function getTypographyVariantEnum(): TypographyVariantEnum
    {
        return match ($this) {
            self::LARGE => TypographyVariantEnum::BODY_MEDIUM,
            self::SMALL => TypographyVariantEnum::BODY_EXTRA_SMALL,
        };
    }
}
