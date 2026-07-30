<?php

namespace App\Pim\Enum\ProviderPortal\Form\Twig\Attributes;

enum ChoiceTypeAttributeEnum: string
{
    case TIP = 'tip';
    case TIP_ICON = 'tip_icon';
    case LIMIT = 'limit';
    case PROTOTYPE = 'prototype';
}
