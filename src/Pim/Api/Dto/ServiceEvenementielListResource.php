<?php

declare(strict_types=1);

namespace App\Pim\Api\Dto;

final readonly class ServiceEvenementielListResource
{
    public function __construct(
        public string $id,
        public ?int $code,
        public ?string $label,
        public ?string $ville,
        public string $status,
        public int $completeness,
        public string $updatedAt,
    ) {}
}
