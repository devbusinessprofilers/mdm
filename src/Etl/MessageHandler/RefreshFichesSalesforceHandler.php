<?php

declare(strict_types=1);

namespace App\Etl\MessageHandler;

use App\Etl\Message\RefreshFichesSalesforce;
use App\Etl\Service\SalesforceClientInterface;
use App\Etl\Service\SalesforceFicheRefresher;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[WithMonologChannel('salesforce')]
#[AsMessageHandler]
final readonly class RefreshFichesSalesforceHandler
{
    public function __construct(
        private SalesforceClientInterface $salesforce,
        private SalesforceFicheRefresher $refresher,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(RefreshFichesSalesforce $message): void
    {
        if (!$this->salesforce->isConfigured()) {
            $this->logger->info('Refresh Salesforce ignoré : connexion non configurée (SALESFORCE_*).');

            return;
        }
        $this->refresher->refresh($message->code);
    }
}
