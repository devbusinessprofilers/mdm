<?php

declare(strict_types=1);

namespace App\Shared\Outbox;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Uid\Ulid;

/**
 * Pose un identifiant d'événement sur tout message dispatché sans en avoir :
 * les messages qui ne passent pas par l'outbox (commandes des contrôleurs,
 * planificateur, relances) reçoivent ainsi le même reçu `processed_message`
 * que les événements outbox, et IdempotencyMiddleware les protège tous.
 */
final readonly class EventIdStampMiddleware implements MiddlewareInterface
{
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if (null === $envelope->last(ReceivedStamp::class) && null === $envelope->last(EventIdStamp::class)) {
            $envelope = $envelope->with(new EventIdStamp((string) new Ulid()));
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
