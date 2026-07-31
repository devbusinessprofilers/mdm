<?php

declare(strict_types=1);

namespace App\Dam\Enum;

enum DocumentAccess: string
{
    case Publishable = 'publishable';
    case Private = 'private';
}
