<?php

declare(strict_types=1);

namespace App\Etl\Service;

use App\Etl\Enum\MarketplaceSyncStatus;
use App\Etl\Message\RemoveFicheFromMarketplace;
use App\Etl\Repository\FicheMarketplaceSyncRepository;
use App\Pim\Entity\Fiche;
use App\Shared\Outbox\OutboxPublisherInterface;

/**
 * Retrait explicite d'une fiche de la marketplace lors d'une dépublication :
 * le scheduler seul ne suffit pas, une fiche repassée en cours y conserverait
 * sinon son dernier snapshot publié. Partagé par la garde photos et la
 * dépublication confirmée de l'éditeur (champ obligatoire vidé).
 */
final readonly class MarketplaceRetrait
{
    public function __construct(
        private FicheMarketplaceSyncRepository $tracking,
        private OutboxPublisherInterface $outbox,
        private MarketplaceClientInterface $client,
    ) {
    }

    /**
     * Enfile le retrait si la fiche est encore diffusée (suivie et non
     * déjà retirée) ; sans client configuré, rien à faire.
     *
     * @return bool vrai si un message de retrait a été enfilé
     */
    public function retirer(Fiche $fiche): bool
    {
        if (!$this->client->isConfigured()) {
            return false;
        }
        $tracked = $this->tracking->forFiche($fiche->id());
        if (null === $tracked || MarketplaceSyncStatus::Removed === $tracked->status()) {
            return false;
        }
        $this->outbox->enqueue(new RemoveFicheFromMarketplace($fiche->idString()));

        return true;
    }
}
