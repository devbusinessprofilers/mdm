<?php

namespace App\Pim\Enum\ProviderPortal;

enum VisibilityPackEnum: string
{
    case FREE = 'free';
    case ESSENTIAL = 'essential';
    case PERFORMANCE = 'performance';
    case TARGETED_AUDIENCE = 'targeted_audience';
}
