<?php

declare(strict_types=1);

namespace App\Shared\Outbox;

final readonly class OutboxRecord
{
    public function __construct(
        public string $id,
        public string $messageType,
        public string $body,
        public string $headers,
        public int $attempts,
        public string $lockedBy,
    ) {
    }
}
