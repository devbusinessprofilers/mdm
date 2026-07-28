<?php

declare(strict_types=1);

namespace App\Shared\Outbox;

final readonly class OutboxRelayResult
{
    public function __construct(
        public int $claimed,
        public int $published,
        public int $failed,
    ) {
    }
}
