<?php

declare(strict_types=1);

namespace App\Shared\Outbox;

enum OutboxStatus: string
{
    case Pending = 'pending';
    case Published = 'published';
    case Failed = 'failed';
}
