<?php

declare(strict_types=1);

namespace App\Shared\Outbox;

use Symfony\Component\Messenger\Stamp\StampInterface;

final readonly class EventIdStamp implements StampInterface
{
    public function __construct(public string $eventId)
    {
    }
}
