<?php

declare(strict_types=1);

namespace App\Shared\Outbox;

use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

final readonly class IdempotencyMiddleware implements MiddlewareInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $eventId = $envelope->last(EventIdStamp::class);

        if (!$eventId instanceof EventIdStamp || null === $envelope->last(ReceivedStamp::class)) {
            return $stack->next()->handle($envelope, $stack);
        }

        $inserted = $this->connection->executeStatement(
            'INSERT IGNORE INTO processed_message (event_id, message_type, processed_at) VALUES (:event_id, :message_type, :processed_at)',
            [
                'event_id' => $eventId->eventId,
                'message_type' => $envelope->getMessage()::class,
                'processed_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ],
        );

        if (0 === $inserted) {
            return $envelope;
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
