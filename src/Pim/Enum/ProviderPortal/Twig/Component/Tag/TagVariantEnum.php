<?php

namespace App\Pim\Enum\ProviderPortal\Twig\Component\Tag;

use App\Pim\Enum\ProviderPortal\Twig\Component\Typography\TypographyTextColorEnum;

enum TagVariantEnum: string
{
    case ERROR = 'error'; // Rouge
    case NEUTRAL = 'neutral'; // Gris
    case OLD_GOLD = 'old-gold'; // Premium
    case PRIMARY = 'primary'; // Néon
    case PRIMARY_3 = 'primary-3'; // Bleu
    case PEACH = 'peach'; // Pêche
    case SUCCESS = 'success'; // Vert
    case SUCCESS_PASTEL = 'success-pastel'; // Vert pâle

    public function getTypographyTextColorEnum(): TypographyTextColorEnum
    {
        return match ($this) {
            self::ERROR => TypographyTextColorEnum::ERROR,
            self::NEUTRAL => TypographyTextColorEnum::NEUTRAL_500,
            self::OLD_GOLD, self::PRIMARY, self::PRIMARY_3, self::SUCCESS => TypographyTextColorEnum::NEUTRAL_100,
            self::PEACH => TypographyTextColorEnum::NEUTRAL_900,
            self::SUCCESS_PASTEL => TypographyTextColorEnum::SUCCESS,
        };
    }
}
