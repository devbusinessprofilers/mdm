<?php

namespace App\Pim\Enum\ProviderPortal\Form\Twig\Attributes;

enum TextTypeAttributeEnum: string
{
    case TOOLTIP = 'tooltip';
    case PLACEHOLDER = 'placeholder';

    case ICON_COLOR = 'iconColor';
    case DISABLED = 'disabled';
    case LEFT_ICON = 'leftIcon';
    case RIGHT_ICON = 'rightIcon';

    case MAX_LENGTH = 'maxLength';
}
