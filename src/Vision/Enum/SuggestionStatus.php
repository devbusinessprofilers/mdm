<?php

declare(strict_types=1);

namespace App\Vision\Enum;

enum SuggestionStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
}
