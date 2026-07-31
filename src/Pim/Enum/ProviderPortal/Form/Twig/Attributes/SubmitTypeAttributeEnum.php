<?php

namespace App\Pim\Enum\ProviderPortal\Form\Twig\Attributes;

enum SubmitTypeAttributeEnum: string
{
    case VARIANT = 'variant';
    case SIZE = 'size';
    case TEXT_COLOR = 'textColor';
    case ICON_SIZE = 'iconSize';
    case DISABLED = 'disabled';
    case FULL = 'full';
    case ICON = 'icon';
}
