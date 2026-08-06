<?php

declare(strict_types=1);

namespace App\Dam\Enum;

enum DuplicateReviewStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Resolved = 'resolved';
}
