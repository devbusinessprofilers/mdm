<?php

declare(strict_types=1);

namespace App\Pim\ReadModel;

final readonly class GlobalSearchPage
{
    /** @param list<GlobalSearchResult> $results */
    public function __construct(
        public array $results,
        public ?string $nextCursor,
        public int $totalCount,
    ) {
    }
}
