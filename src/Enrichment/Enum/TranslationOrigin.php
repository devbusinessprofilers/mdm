<?php

declare(strict_types=1);

namespace App\Enrichment\Enum;

enum TranslationOrigin: string
{
    case Google = 'google';
    case Manual = 'manual';
}
