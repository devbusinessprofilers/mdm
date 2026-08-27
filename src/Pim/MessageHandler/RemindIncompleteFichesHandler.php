<?php

declare(strict_types=1);

namespace App\Pim\MessageHandler;

use App\Pim\Message\RemindIncompleteFiches;
use App\Pim\Service\RelanceCompletudePlanificateur;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Préparation hebdomadaire (lundi 8h) : constitue le lot de relances de
 * complétude planifiées, vérifiable dans /admin/relances-completude avant
 * l'envoi de 14h. N'envoie aucun mail.
 */
#[WithMonologChannel('mail')]
#[AsMessageHandler]
final readonly class RemindIncompleteFichesHandler
{
    public function __construct(
        private RelanceCompletudePlanificateur $planificateur,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(RemindIncompleteFiches $message): void
    {
        $count = $this->planificateur->preparer();
        $this->logger->info('Lot de relances de complétude préparé.', ['relances' => $count]);
    }
}
