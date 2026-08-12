<?php

declare(strict_types=1);

namespace App\Etl\Service;

use App\Etl\Message\SyncLovDictionary;
use App\Shared\Outbox\OutboxPublisherInterface;

/**
 * Enfile la synchronisation du dictionnaire LOV vers la marketplace. À
 * appeler après toute mutation du dictionnaire (création, renommage,
 * désactivation, traduction aboutie) : le snapshot étant complet et
 * idempotent, des enfilements rapprochés sont sans danger — la garde de
 * séquence côté marketplace élimine les messages périmés.
 */
final readonly class MarketplaceLovSyncScheduler
{
    public function __construct(
        private MarketplaceClientInterface $client,
        private OutboxPublisherInterface $outbox,
    ) {
    }

    public function schedule(): void
    {
        if (!$this->client->isConfigured()) {
            return;
        }
        $this->outbox->enqueue(new SyncLovDictionary());
    }
}
