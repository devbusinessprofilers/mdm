<?php

declare(strict_types=1);

namespace App\Etl\MessageHandler;

use App\Etl\Enum\MarketplaceSyncStatus;
use App\Etl\Message\RemoveFicheFromMarketplace;
use App\Etl\Repository\FicheMarketplaceSyncRepository;
use App\Etl\Service\MarketplaceClientInterface;
use App\Etl\Service\MarketplaceSyncScheduler;
use App\Pim\Entity\Fiche;
use App\Pim\Enum\StatutFiche;
use App\Pim\Repository\FicheRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Ulid;

#[AsMessageHandler]
final readonly class RemoveFicheFromMarketplaceHandler
{
    public function __construct(
        private FicheRepository $fiches,
        private FicheMarketplaceSyncRepository $tracking,
        private MarketplaceSyncScheduler $scheduler,
        private MarketplaceClientInterface $client,
    ) {
    }

    public function __invoke(RemoveFicheFromMarketplace $message): void
    {
        $ficheId = Ulid::fromString($message->ficheId);
        $tracked = $this->tracking->forFiche($ficheId);
        // Jamais envoyée ou déjà retirée : rien à dépublier.
        if (null === $tracked || MarketplaceSyncStatus::Removed === $tracked->status()) {
            return;
        }
        $fiche = $this->fiches->find($ficheId);
        // Rediffusée entre-temps : le Sync qui suit ce changement fera foi.
        if (
            $fiche instanceof Fiche
            && StatutFiche::Publiee === $fiche->status()
            && $this->scheduler->diffusable($fiche)
        ) {
            return;
        }
        $sequence = (string) new Ulid();
        $this->client->removeFiche($tracked->code(), $sequence);
        $tracked->recordRemoved($sequence);
    }
}
