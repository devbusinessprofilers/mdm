<?php

declare(strict_types=1);

namespace App\Pim\ReadModel;

final readonly class LieuListPage
{
    /** @param list<LieuListItem> $items */
    public function __construct(
        public array $items,
        public ?string $nextCursor,
    ) {
    }
}
