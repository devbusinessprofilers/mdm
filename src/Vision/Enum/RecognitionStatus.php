<?php

declare(strict_types=1);

namespace App\Vision\Enum;

enum RecognitionStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Ready = 'ready';
    case PartiallyReviewed = 'partially_reviewed';
    case Reviewed = 'reviewed';
    case Failed = 'failed';

    /** La reco occupe encore la file : une seule analyse à la fois par ressource. */
    public function isActive(): bool
    {
        return in_array($this, [self::Queued, self::Processing, self::Ready], true);
    }
}
