<?php

declare(strict_types=1);

namespace App\Shared\Metrics;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\Event\WorkerStartedEvent;
use Symfony\Component\Messenger\Event\WorkerStoppedEvent;

/**
 * Branche le heartbeat sur le cycle de vie des workers Messenger.
 * WorkerRunningEvent se déclenche à chaque itération de boucle, y compris au
 * repos (--sleep=1 ⇒ ~1/s) : c'est le reporter qui throttle les écritures.
 */
final readonly class WorkerHeartbeatListener
{
    public function __construct(private WorkerHeartbeatReporter $reporter)
    {
    }

    #[AsEventListener]
    public function onStarted(WorkerStartedEvent $event): void
    {
        $this->reporter->start($event->getWorker()->getMetadata()->getTransportNames());
    }

    #[AsEventListener]
    public function onRunning(WorkerRunningEvent $event): void
    {
        $this->reporter->beat();
    }

    #[AsEventListener]
    public function onStopped(WorkerStoppedEvent $event): void
    {
        $this->reporter->stop();
    }
}
