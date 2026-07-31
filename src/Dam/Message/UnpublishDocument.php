<?php

declare(strict_types=1);

namespace App\Dam\Message;

final readonly class UnpublishDocument
{
    public function __construct(
        public string $resourceId,
        public string $publicStorageKey,
    ) {
    }
}
