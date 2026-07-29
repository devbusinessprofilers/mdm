<?php

declare(strict_types=1);

namespace App\Shared\Search;

final readonly class SearchResult
{
    public function __construct(public string $id, public float $score)
    {
    }
}
