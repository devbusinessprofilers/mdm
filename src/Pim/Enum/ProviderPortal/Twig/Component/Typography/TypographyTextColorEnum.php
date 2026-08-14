<?php

namespace App\Pim\Enum\ProviderPortal\Twig\Component\Typography;

enum TypographyTextColorEnum: string
{
    case CONTROLLED = 'controlled';
    case DARK = 'dark';
    case ERROR = 'error';
    case LIGHT = 'light';
    case NEUTRAL_100 = 'neutral-100';
    case NEUTRAL_400 = 'neutral-400';
    case NEUTRAL_500 = 'neutral-500';
    case NEUTRAL_900 = 'neutral-900';
    case PRIMARY = 'primary';
    case PRIMARY_3 = 'primary-3';
    case SUCCESS = 'success';
    case OLD_GOLD = 'old-gold';
}
