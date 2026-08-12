<?php

declare(strict_types=1);

namespace App\Shared\Alert\MessageHandler;

use App\Shared\Alert\AlertNotifier;
use App\Shared\Alert\Message\CheckFailedQueue;
use App\Shared\Outbox\OutboxStatus;
use App\Shared\Service\ParametreProviderInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CheckFailedQueueHandler
{
    public function __construct(
        private Connection $connection,
        private AlertNotifier $notifier,
        private ParametreProviderInterface $parametres,
    ) {}

    public function __invoke(CheckFailedQueue $message): void
    {
        $threshold = $this->parametres->int('alerte.seuil_file_echec');
        // Deux origines d'échec surveillées : la queue failed de Messenger
        // et les événements outbox abandonnés après épuisement des tentatives.
        $messengerFailed = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'failed'",
        );
        $outboxFailed = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM outbox_message WHERE status = :status',
            ['status' => OutboxStatus::Failed->value],
        );
        $failed = $messengerFailed + $outboxFailed;
        if ($failed < $threshold) {
            return;
        }
        $this->notifier->notify(
            'failed_queue',
            'failed_queue_threshold',
            sprintf('Messages en échec : %d (messenger %d, outbox %d, seuil %d)', $failed, $messengerFailed, $outboxFailed, $threshold),
            sprintf(
                "%d messages en échec (seuil d'alerte : %d), dont %d dans la queue failed de Messenger et %d dans l'outbox.\n\nDiagnostic : php bin/console messenger:failed:show / php bin/console app:outbox:failed:show\nRejeu : php bin/console messenger:failed:retry / php bin/console app:outbox:failed:retry",
                $failed,
                $threshold,
                $messengerFailed,
                $outboxFailed,
            ),
        );
    }
}
