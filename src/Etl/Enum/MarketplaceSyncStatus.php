<?php

declare(strict_types=1);

namespace App\Etl\Enum;

enum MarketplaceSyncStatus: string
{
    case Synced = 'synced';
    case Failed = 'failed';
    case Removed = 'removed';
}
