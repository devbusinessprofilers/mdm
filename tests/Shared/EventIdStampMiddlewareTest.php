<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Shared\Outbox\EventIdStamp;
use App\Shared\Outbox\EventIdStampMiddleware;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\StackMiddleware;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

final class EventIdStampMiddlewareTest extends TestCase
{
    public function testUnDispatchDirectRecoitUnIdentifiantDEvenement(): void
    {
        $envelope = (new EventIdStampMiddleware())->handle(new Envelope(new \stdClass()), new StackMiddleware());

        self::assertInstanceOf(EventIdStamp::class, $envelope->last(EventIdStamp::class));
    }

    public function testUnIdentifiantExistantEstConserve(): void
    {
        $envelope = (new EventIdStampMiddleware())->handle(new Envelope(new \stdClass(), [new EventIdStamp('01JEXISTANT')]), new StackMiddleware());

        self::assertSame('01JEXISTANT', $envelope->last(EventIdStamp::class)?->eventId);
        self::assertCount(1, $envelope->all(EventIdStamp::class));
    }

    public function testUnMessageRecuParUnWorkerNEstPasRetampille(): void
    {
        $envelope = (new EventIdStampMiddleware())->handle(new Envelope(new \stdClass(), [new ReceivedStamp('pim')]), new StackMiddleware());

        self::assertNull($envelope->last(EventIdStamp::class));
    }
}
