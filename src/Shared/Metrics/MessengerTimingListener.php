<?php

declare(strict_types=1);

namespace App\Shared\Metrics;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

/**
 * Mesure le temps de traitement des messages consommés par les workers.
 * La clé du suivi est l'objet message lui-même : l'Envelope est clonée à
 * chaque stamp ajouté entre la réception et la fin du traitement, seul le
 * message interne reste la même instance.
 * Chaque durée part vers deux consommateurs : les compteurs Prometheus
 * (MetricsCollector) et le heartbeat de /admin/performance.
 */
final readonly class MessengerTimingListener
{
    /** @var \WeakMap<object, float> */
    private \WeakMap $started;

    public function __construct(
        private MetricsCollector $metrics,
        private WorkerHeartbeatReporter $heartbeat,
    ) {
        /** @var \WeakMap<object, float> $map */
        $map = new \WeakMap();
        $this->started = $map;
    }

    #[AsEventListener]
    public function onReceived(WorkerMessageReceivedEvent $event): void
    {
        $message = $event->getEnvelope()->getMessage();
        $this->started[$message] = hrtime(true) / 1e9;
        $this->heartbeat->messageStarted($message::class);
    }

    #[AsEventListener]
    public function onHandled(WorkerMessageHandledEvent $event): void
    {
        $this->record($event->getEnvelope()->getMessage(), 'handled', $event->getReceiverName());
    }

    #[AsEventListener]
    public function onFailed(WorkerMessageFailedEvent $event): void
    {
        $this->record($event->getEnvelope()->getMessage(), $event->willRetry() ? 'retried' : 'failed', $event->getReceiverName());
    }

    private function record(object $message, string $outcome, string $transport): void
    {
        $start = $this->started[$message] ?? null;
        if (null === $start) {
            return;
        }
        unset($this->started[$message]);
        $seconds = max(0.0, hrtime(true) / 1e9 - $start);
        $this->metrics->recordMessage($message::class, $outcome, $seconds);
        $this->heartbeat->recordMessage($message::class, $transport, $outcome, $seconds);
    }
}
