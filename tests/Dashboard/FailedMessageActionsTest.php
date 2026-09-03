<?php

declare(strict_types=1);

namespace App\Tests\Dashboard;

use App\Dashboard\Service\FailedMessageActions;
use App\Shared\Outbox\EventIdStamp;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

final class FailedMessageActionsTest extends TestCase
{
    public function testLaRelanceConserveLIdentifiantDEvenementEtLeTransportDOrigine(): void
    {
        $message = new \stdClass();
        $enEchec = new Envelope($message, [new EventIdStamp('01JEVENT'), new SentToFailureTransportStamp('pim')]);
        $transport = new class($enEchec) implements TransportInterface, ListableReceiverInterface {
            public ?Envelope $rejete = null;

            public function __construct(private Envelope $envelope) {}

            public function get(): iterable { return []; }

            public function ack(Envelope $envelope): void {}

            public function reject(Envelope $envelope): void { $this->rejete = $envelope; }

            public function send(Envelope $envelope): Envelope { return $envelope; }

            public function all(?int $limit = null): iterable { yield $this->envelope; }

            public function find(mixed $id): ?Envelope { return '42' === (string) $id ? $this->envelope : null; }
        };
        $bus = new class implements MessageBusInterface {
            public ?Envelope $envoye = null;

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                return $this->envoye = new Envelope($message, $stamps);
            }
        };

        $actions = new FailedMessageActions($transport, $bus);

        self::assertTrue($actions->reessayer('42'));
        self::assertSame($message, $bus->envoye?->getMessage());
        self::assertSame('01JEVENT', $bus->envoye->last(EventIdStamp::class)?->eventId);
        self::assertSame(['pim'], $bus->envoye->last(TransportNamesStamp::class)?->getTransportNames());
        self::assertSame($enEchec, $transport->rejete);
        self::assertFalse($actions->reessayer('inconnu'));
    }
}
