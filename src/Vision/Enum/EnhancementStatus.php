<?php

declare(strict_types=1);

namespace App\Vision\Enum;

enum EnhancementStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Ready = 'ready';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Failed = 'failed';

    /** Le job occupe encore la file : inutile d'en relancer un sur le même média. */
    public function isActive(): bool
    {
        return in_array($this, [self::Queued, self::Processing, self::Ready], true);
    }
}
