<?php

namespace App\Pim\Enum\ProviderPortal\Form\Twig\Attributes;

enum NumberTypeAttributeEnum: string
{
    case TOOLTIP = 'tooltip';
    case PLACEHOLDER = 'placeholder';

    case ICON_COLOR = 'iconColor';
    case DISABLED = 'disabled';
    case LEFT_ICON = 'leftIcon';
    case RIGHT_ICON = 'rightIcon';

    case STEP = 'step';
    case MIN = 'min';
    case MAX = 'max';
}
