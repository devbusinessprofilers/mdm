<?php

declare(strict_types=1);

namespace App\Pim\ReadModel;

final readonly class ServiceEvenementielListPage
{
    /** @param list<ServiceEvenementielListItem> $items */
    public function __construct(
        public array $items,
        public ?string $nextCursor,
    ) {
    }
}
