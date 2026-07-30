<?php

declare(strict_types=1);

namespace App\Dam\Enum;

enum MediaStatus: string
{
    case Uploaded = 'uploaded';
    case Processing = 'processing';
    case Processed = 'processed';
    case Failed = 'failed';
    case Deleting = 'deleting';
    case Deleted = 'deleted';
}
