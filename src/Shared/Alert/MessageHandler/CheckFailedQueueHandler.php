<?php

declare(strict_types=1);

namespace App\Shared\Alert\MessageHandler;

use App\Shared\Alert\AlertNotifier;
use App\Shared\Alert\Message\CheckFailedQueue;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CheckFailedQueueHandler
{
    public function __construct(
        private Connection $connection,
        private AlertNotifier $notifier,
        #[Autowire(env: 'int:ALERT_FAILED_QUEUE_THRESHOLD')]
        private int $threshold,
    ) {}

    public function __invoke(CheckFailedQueue $message): void
    {
        $failed = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'failed'",
        );
        if ($failed < $this->threshold) {
            return;
        }
        $this->notifier->notify(
            'failed_queue',
            'failed_queue_threshold',
            sprintf('Queue failed : %d messages (seuil %d)', $failed, $this->threshold),
            sprintf(
                "La queue failed contient %d messages (seuil d'alerte : %d).\n\nDiagnostic : php bin/console messenger:failed:show\nRejeu : php bin/console messenger:failed:retry",
                $failed,
                $this->threshold,
            ),
        );
    }
}
