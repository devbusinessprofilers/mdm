<?php

declare(strict_types=1);

namespace App\Pim\Import\Dto;

final readonly class ConvertedRow
{
    /**
     * @param list<ConvertedValue>                      $fields
     * @param array<string, list<list<ConvertedValue>>> $collections préfixe => entrées (uniquement les collections touchées)
     * @param list<RowError>                            $errors
     */
    public function __construct(
        public int $lineNumber,
        public ?int $code,
        public array $fields,
        public array $collections,
        public array $errors,
    ) {
    }
}
