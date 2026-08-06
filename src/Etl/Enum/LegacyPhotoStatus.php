<?php

declare(strict_types=1);

namespace App\Etl\Enum;

enum LegacyPhotoStatus: string
{
    case Pending = 'pending';
    case Done = 'done';
    case Error = 'error';
    case MissingFile = 'missing_file';
    case Invalid = 'invalid';
    case SkippedLimit = 'skipped_limit';
}
