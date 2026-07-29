<?php

declare(strict_types=1);

namespace App\Shared\Search;

final readonly class SearchQuery
{
    /** @param array<string, scalar|null> $filters */
    public function __construct(
        public string $text,
        public array $filters = [],
        public int $limit = 20,
        public ?string $cursor = null,
    ) {
        if ($limit < 1 || $limit > 100) {
            throw new \InvalidArgumentException('Search limit must be between 1 and 100.');
        }
    }
}
