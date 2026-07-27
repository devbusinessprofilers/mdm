<?php

declare(strict_types=1);

namespace App\Shared\Service;

interface SearchEngineInterface
{
    /**
     * @param array<string, scalar|null> $filters
     *
     * @return list<array<string, mixed>>
     */
    public function search(string $query, array $filters = [], int $limit = 20): array;
}
