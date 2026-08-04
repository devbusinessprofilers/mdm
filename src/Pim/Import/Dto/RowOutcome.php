<?php

declare(strict_types=1);

namespace App\Pim\Import\Dto;

final readonly class RowOutcome
{
    /** @param list<RowError> $errors */
    public function __construct(
        public RowAction $action,
        public array $errors = [],
    ) {
    }

    /** @param list<RowError> $errors */
    public static function failed(array $errors): self
    {
        return new self(RowAction::Failed, $errors);
    }
}
