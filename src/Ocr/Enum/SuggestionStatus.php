<?php

declare(strict_types=1);

namespace App\Ocr\Enum;

enum SuggestionStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
}
