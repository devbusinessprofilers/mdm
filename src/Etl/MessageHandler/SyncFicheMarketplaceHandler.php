<?php

declare(strict_types=1);

namespace App\Etl\MessageHandler;

use App\Etl\Entity\FicheMarketplaceSync;
use App\Etl\Message\SyncFicheMarketplace;
use App\Etl\Repository\FicheMarketplaceSyncRepository;
use App\Etl\Service\MarketplaceClientInterface;
use App\Etl\Service\MarketplaceFichePayloadBuilder;
use App\Etl\Service\MarketplaceSyncScheduler;
use App\Pim\Entity\Fiche;
use App\Pim\Enum\StatutFiche;
use App\Pim\Repository\FicheRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Ulid;

#[AsMessageHandler]
final readonly class SyncFicheMarketplaceHandler
{
    public function __construct(
        private FicheRepository $fiches,
        private FicheMarketplaceSyncRepository $tracking,
        private MarketplaceSyncScheduler $scheduler,
        private MarketplaceFichePayloadBuilder $payloadBuilder,
        private MarketplaceClientInterface $client,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(SyncFicheMarketplace $message): void
    {
        $fiche = $this->fiches->find(Ulid::fromString($message->ficheId));
        // L'état a pu changer depuis l'enfilement : la décision est réévaluée
        // ici et le payload reconstruit, un message en retard reste donc sûr.
        if (
            !$fiche instanceof Fiche
            || StatutFiche::Publiee !== $fiche->status()
            || !$this->scheduler->diffusable($fiche)
        ) {
            return;
        }
        $sequence = (string) new Ulid();
        $payload = $this->payloadBuilder->build($fiche);
        $payload['sequence'] = $sequence;
        $this->client->upsertFiche($fiche->code(), $payload);
        $tracked = $this->tracking->forFiche($fiche->id());
        if (null === $tracked) {
            $tracked = new FicheMarketplaceSync($fiche->id(), $fiche->code());
            $this->entityManager->persist($tracked);
        }
        $tracked->recordSynced($sequence);
    }
}
