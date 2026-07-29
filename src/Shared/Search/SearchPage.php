<?php

declare(strict_types=1);

namespace App\Shared\Search;

final readonly class SearchPage
{
    /** @param list<SearchResult> $results */
    public function __construct(public array $results, public ?string $nextCursor, public int $totalCount)
    {
    }
}
