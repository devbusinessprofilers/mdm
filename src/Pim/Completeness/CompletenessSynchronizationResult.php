<?php

declare(strict_types=1);

namespace App\Pim\Completeness;

use App\Pim\Enum\TypeFiche;

final readonly class CompletenessSynchronizationResult
{
    public function __construct(
        public TypeFiche $type,
        public int $created,
        public int $deactivated,
        public int $revision,
        public bool $recalculationScheduled,
    ) {
    }
}
