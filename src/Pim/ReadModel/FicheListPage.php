<?php

declare(strict_types=1);

namespace App\Pim\ReadModel;

final readonly class FicheListPage
{
    /** @param list<GlobalSearchItem> $items */
    public function __construct(
        public array $items,
        public ?string $nextCursor,
    ) {
    }
}
