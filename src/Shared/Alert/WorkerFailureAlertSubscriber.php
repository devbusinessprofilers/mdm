<?php

declare(strict_types=1);

namespace App\Shared\Alert;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

/** Alerte quand un message asynchrone échoue définitivement (retries épuisés). */
#[AsEventListener]
final readonly class WorkerFailureAlertSubscriber
{
    public function __construct(private AlertNotifier $notifier)
    {
    }

    public function __invoke(WorkerMessageFailedEvent $event): void
    {
        if ($event->willRetry()) {
            return;
        }
        $messageClass = $event->getEnvelope()->getMessage()::class;
        $throwable = $event->getThrowable();
        $this->notifier->notify(
            'worker_failure',
            $messageClass.':'.$throwable::class,
            sprintf('Message %s en échec définitif', $messageClass),
            sprintf(
                "Le message %s a épuisé ses tentatives sur le transport %s.\n\nErreur : %s\n%s\n\nLe message est dans la queue failed : php bin/console messenger:failed:show",
                $messageClass,
                $event->getReceiverName(),
                $throwable::class,
                $throwable->getMessage(),
            ),
        );
    }
}
