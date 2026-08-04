<?php

declare(strict_types=1);

namespace App\Pim\ReadModel;

use App\Pim\Enum\StatutFiche;
use App\Pim\Enum\TypeFiche;

final readonly class GlobalSearchItem
{
    public function __construct(
        public string $id,
        public TypeFiche $type,
        public int $code,
        public ?string $label,
        public ?string $ville,
        public StatutFiche $status,
        public int $completeness,
        public \DateTimeImmutable $updatedAt,
    ) {
    }
}
