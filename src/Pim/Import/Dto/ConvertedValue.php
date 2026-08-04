<?php

declare(strict_types=1);

namespace App\Pim\Import\Dto;

use App\Pim\Import\Schema\ColumnDefinition;

final readonly class ConvertedValue
{
    public function __construct(
        public ColumnDefinition $column,
        public mixed $value,
        public bool $clear = false,
    ) {
    }
}
