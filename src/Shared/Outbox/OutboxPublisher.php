<?php

declare(strict_types=1);

namespace App\Shared\Outbox;

use App\Shared\Entity\OutboxMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Sender\SendersLocatorInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Uid\Ulid;

final readonly class OutboxPublisher implements OutboxPublisherInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SerializerInterface $serializer,
        private SendersLocatorInterface $sendersLocator,
    ) {
    }

    public function enqueue(object $message): string
    {
        $eventId = (string) new Ulid();
        $envelope = new Envelope($message, [new EventIdStamp($eventId)]);

        foreach ($this->sendersLocator->getSenders($envelope) as $sender) {
            unset($sender);
            $encoded = $this->serializer->encode($envelope);
            $this->entityManager->persist(OutboxMessage::pending(
                id: $eventId,
                messageType: $message::class,
                body: $encoded['body'],
                headers: $encoded['headers'] ?? [],
                occurredAt: new \DateTimeImmutable(),
            ));

            return $eventId;
        }

        throw new \LogicException(sprintf('The message "%s" has no asynchronous Messenger sender.', $message::class));
    }
}
