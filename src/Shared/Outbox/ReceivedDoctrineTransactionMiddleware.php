<?php

declare(strict_types=1);

namespace App\Shared\Outbox;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

/**
 * Rend transactionnels les handlers exécutés côté worker (ReceivedStamp) :
 * la transaction est ouverte avant le handler et englobe donc aussi ses
 * appels HTTP sortants (PUT/DELETE marketplace) — comportement voulu,
 * borné par les timeouts du client HTTP. Les dispatch synchrones (sans
 * ReceivedStamp) traversent le middleware sans transaction.
 */
final readonly class ReceivedDoctrineTransactionMiddleware implements MiddlewareInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if (null === $envelope->last(ReceivedStamp::class)) {
            return $stack->next()->handle($envelope, $stack);
        }

        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();
        $success = false;

        try {
            $handledEnvelope = $stack->next()->handle($envelope, $stack);
            $this->entityManager->flush();
            $connection->commit();
            $success = true;

            return $handledEnvelope;
        } catch (HandlerFailedException $error) {
            throw new HandlerFailedException(
                $error->getEnvelope()->withoutAll(HandledStamp::class),
                $error->getWrappedExceptions(),
            );
        } finally {
            if (!$success && $connection->isTransactionActive()) {
                $connection->rollBack();
            }
        }
    }
}
