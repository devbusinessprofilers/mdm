<?php

declare(strict_types=1);

namespace App\Pim\Completeness;

use App\Pim\Enum\TypeFiche;

final readonly class CompletenessFieldDefinition
{
    public function __construct(
        public TypeFiche $type,
        public string $code,
        public string $label,
        public string $path,
        public ?int $defaultTargetLength = null,
        public ?string $conditionPath = null,
        public mixed $conditionValue = null,
        public ?string $conditionLabel = null,
    ) {
    }
}
