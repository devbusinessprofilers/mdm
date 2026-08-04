<?php

declare(strict_types=1);

namespace App\Pim\Import\Schema;

final readonly class CollectionSchema
{
    /**
     * @param class-string               $entryClass
     * @param list<ColumnDefinition>     $columns    en-têtes relatifs, préfixés {prefix}_{i}_ dans le CSV
     */
    public function __construct(
        public string $prefix,
        public int $max,
        public string $entryClass,
        public string $adder,
        public string $getter,
        public array $columns,
    ) {
    }

    public function header(int $index, ColumnDefinition $column): string
    {
        return sprintf('%s_%d_%s', $this->prefix, $index, $column->header);
    }
}
