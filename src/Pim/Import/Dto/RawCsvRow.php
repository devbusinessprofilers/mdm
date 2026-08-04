<?php

declare(strict_types=1);

namespace App\Pim\Import\Dto;

final readonly class RawCsvRow
{
    /** @param array<string, string> $cells indexées par en-tête de colonne */
    public function __construct(
        public int $lineNumber,
        public array $cells,
    ) {
    }

    public function cell(string $header): string
    {
        return trim($this->cells[$header] ?? '');
    }
}
