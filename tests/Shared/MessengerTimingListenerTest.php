<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Shared\Metrics\MessengerTimingListener;
use App\Shared\Metrics\MetricsCollector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Stamp\DelayStamp;

final class MessengerTimingListenerTest extends TestCase
{
    private MetricsCollector $metrics;
    private MessengerTimingListener $listener;

    protected function setUp(): void
    {
        $this->metrics = new MetricsCollector(new ArrayAdapter());
        $this->listener = new MessengerTimingListener($this->metrics);
    }

    public function testHandledMessageIsTimedEvenWhenEnvelopeIsCloned(): void
    {
        $message = new \stdClass();
        $received = new Envelope($message);
        $this->listener->onReceived(new WorkerMessageReceivedEvent($received, 'dam'));
        // L'Envelope est clonée par chaque stamp ajouté pendant le traitement.
        $this->listener->onHandled(new WorkerMessageHandledEvent($received->with(new DelayStamp(0)), 'dam'));

        $counters = $this->metrics->all();
        self::assertSame(1, $counters['messages_total.stdClass.handled']);
        self::assertSame(1, $counters['message_seconds_count.stdClass']);
        self::assertGreaterThanOrEqual(0.0, $counters['message_seconds_sum.stdClass']);
    }

    public function testFailedMessageOutcomeDependsOnRetry(): void
    {
        $retried = new Envelope(new \stdClass());
        $this->listener->onReceived(new WorkerMessageReceivedEvent($retried, 'dam'));
        $event = new WorkerMessageFailedEvent($retried, 'dam', new \RuntimeException('boom'));
        $event->setForRetry();
        $this->listener->onFailed($event);

        $failed = new Envelope(new \stdClass());
        $this->listener->onReceived(new WorkerMessageReceivedEvent($failed, 'dam'));
        $this->listener->onFailed(new WorkerMessageFailedEvent($failed, 'dam', new \RuntimeException('boom')));

        $counters = $this->metrics->all();
        self::assertSame(1, $counters['messages_total.stdClass.retried']);
        self::assertSame(1, $counters['messages_total.stdClass.failed']);
    }

    public function testHandledWithoutReceivedIsIgnored(): void
    {
        $this->listener->onHandled(new WorkerMessageHandledEvent(new Envelope(new \stdClass()), 'dam'));

        self::assertSame([], $this->metrics->all());
    }
}
