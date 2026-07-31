<?php

declare(strict_types=1);

namespace App\Dam\Enum;

enum MediaKind: string
{
    case Image = 'image';
    case Document = 'document';
}
