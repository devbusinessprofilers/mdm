<?php

declare(strict_types=1);

namespace App\Pim\Import\Schema;

final readonly class ColumnDefinition
{
    /** @param ?class-string<\BackedEnum> $enumClass */
    public function __construct(
        public string $header,
        public ColumnKind $kind,
        public string $target,
        public ?string $lovAttribute = null,
        public ?string $enumClass = null,
        public bool $required = false,
        public ?int $maxLength = null,
        public string $help = '',
        public ?string $targetPath = null,
        public bool $nullable = true,
    ) {
    }

    public function setter(): string
    {
        return 'change'.ucfirst($this->target);
    }
}
