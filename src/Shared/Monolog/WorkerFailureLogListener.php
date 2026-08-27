<?php

declare(strict_types=1);

namespace App\Shared\Monolog;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;

/**
 * Trace normalisée des échecs définitifs de messages (retries épuisés) :
 * indépendante du wording des logs internes de Symfony, filtrable dans la
 * visionneuse de /admin/performance. L'alerte humaine reste portée par
 * WorkerFailureAlertSubscriber.
 */
#[AsEventListener]
#[WithMonologChannel('messenger')]
final readonly class WorkerFailureLogListener
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function __invoke(WorkerMessageFailedEvent $event): void
    {
        if ($event->willRetry()) {
            return;
        }
        $throwable = $event->getThrowable();
        $this->logger->error('worker.message.failed_definitively', [
            'message_class' => $event->getEnvelope()->getMessage()::class,
            'transport' => $event->getReceiverName(),
            'exception_class' => $throwable::class,
            'exception_message' => $throwable->getMessage(),
            'attempts' => count($event->getEnvelope()->all(RedeliveryStamp::class)) + 1,
        ]);
    }
}
