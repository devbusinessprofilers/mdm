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
 * Garde les envois HTTP sans connexion DB et rend transactionnels les handlers workers.
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
