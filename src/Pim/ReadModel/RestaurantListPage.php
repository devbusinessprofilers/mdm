<?php

declare(strict_types=1);

namespace App\Pim\ReadModel;

final readonly class RestaurantListPage
{
    /** @param list<RestaurantListItem> $items */
    public function __construct(
        public array $items,
        public ?string $nextCursor,
    ) {
    }
}
