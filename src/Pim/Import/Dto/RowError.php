<?php

declare(strict_types=1);

namespace App\Pim\Import\Dto;

final readonly class RowError
{
    public function __construct(
        public int $lineNumber,
        public ?string $column,
        public string $message,
    ) {
    }
}
