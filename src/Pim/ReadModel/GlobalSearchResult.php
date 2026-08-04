<?php

declare(strict_types=1);

namespace App\Pim\ReadModel;

final readonly class GlobalSearchResult
{
    public function __construct(
        public GlobalSearchItem $item,
        public string $showUrl,
        public string $editUrl,
    ) {
    }
}
