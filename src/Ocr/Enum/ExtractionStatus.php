<?php

declare(strict_types=1);

namespace App\Ocr\Enum;

enum ExtractionStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Ready = 'ready';
    case PartiallyReviewed = 'partially_reviewed';
    case Reviewed = 'reviewed';
    case Failed = 'failed';
}
