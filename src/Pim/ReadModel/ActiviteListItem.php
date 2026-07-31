<?php

declare(strict_types=1);

namespace App\Pim\ReadModel;

use App\Pim\Enum\StatutFiche;

final readonly class ActiviteListItem
{
    public function __construct(
        public string $id,
        public ?int $code,
        public ?string $label,
        public ?string $ville,
        public StatutFiche $status,
        public int $completeness,
        public \DateTimeImmutable $updatedAt,
    ) {
    }
}
