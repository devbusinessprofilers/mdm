<?php

declare(strict_types=1);

namespace App\Etl\MessageHandler;

use App\Etl\Entity\FicheMarketplaceSync;
use App\Etl\Enum\MarketplaceSyncStatus;
use App\Etl\Message\PruneMarketplacePhotos;
use App\Etl\Message\RemoveFicheFromMarketplace;
use App\Etl\Message\SyncFicheMarketplace;
use App\Etl\Repository\FicheMarketplaceSyncRepository;
use App\Etl\Service\MarketplaceApiException;
use App\Etl\Service\MarketplaceClientInterface;
use App\Etl\Service\MarketplaceFichePayloadBuilder;
use App\Etl\Service\MarketplacePhotoPolicy;
use App\Etl\Service\MarketplaceSyncScheduler;
use App\Pim\Entity\Fiche;
use App\Pim\Enum\StatutFiche;
use App\Pim\Repository\FicheRepository;
use App\Shared\Outbox\OutboxPublisherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Ulid;

#[WithMonologChannel('marketplace_sync')]
#[AsMessageHandler]
final readonly class SyncFicheMarketplaceHandler
{
    public function __construct(
        private FicheRepository $fiches,
        private FicheMarketplaceSyncRepository $tracking,
        private MarketplaceSyncScheduler $scheduler,
        private MarketplaceFichePayloadBuilder $payloadBuilder,
        private MarketplacePhotoPolicy $photoPolicy,
        private MarketplaceClientInterface $client,
        private OutboxPublisherInterface $outbox,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(SyncFicheMarketplace $message): void
    {
        $fiche = $this->fiches->parId($message->ficheId);
        // L'état a pu changer depuis l'enfilement : la décision est réévaluée
        // ici et le payload reconstruit, un message en retard reste donc sûr.
        if (
            !$fiche instanceof Fiche
            || StatutFiche::Publiee !== $fiche->status()
            || !$this->scheduler->diffusable($fiche)
        ) {
            return;
        }
        // Sous les obligations photos (minimum du type, plancher à une photo), le
        // snapshot ne doit pas être poussé : les photos supprimées sont
        // purgées puis la fiche est dépubliée jusqu'au rétablissement. Les
        // fiches encore sur imagerie legacy (aucune photo PIM) ne sont pas
        // concernées.
        if (!$this->photoPolicy->allowsPublication($fiche)) {
            $tracked = $this->tracking->forFiche($fiche->id());
            if (null !== $tracked) {
                $this->outbox->enqueue(new PruneMarketplacePhotos($fiche->idString()));
                if (MarketplaceSyncStatus::Removed !== $tracked->status()) {
                    $this->outbox->enqueue(new RemoveFicheFromMarketplace($fiche->idString()));
                }
            }

            return;
        }
        $sequence = (string) new Ulid();
        $payload = $this->payloadBuilder->build($fiche);
        $payload['sequence'] = $sequence;
        try {
            $applied = $this->client->upsertFiche($fiche->code(), $payload);
        } catch (MarketplaceApiException $exception) {
            throw $exception->pourMessenger();
        }
        // Conflit de séquence : la marketplace détient déjà un état plus
        // récent, l'état local ne doit pas être marqué synchronisé avec
        // une séquence refusée.
        if (!$applied) {
            $this->logger->notice('Fiche ignorée par la marketplace : elle détient déjà une séquence plus récente.', [
                'code' => $fiche->code(),
                'sequence' => $sequence,
            ]);

            return;
        }
        $tracked = $this->tracking->forFiche($fiche->id());
        if (null === $tracked) {
            $tracked = new FicheMarketplaceSync($fiche->id(), $fiche->code());
            $this->entityManager->persist($tracked);
        }
        $tracked->recordSynced($sequence);
        // Transition legacy : un payload sans photos ne touche pas aux photos
        // côté marketplace. Une purge dédiée retire les éventuelles lignes PIM
        // devenues orphelines (fiche dont toutes les photos ont été
        // supprimées) ; pour une fiche réellement legacy, elle est sans effet.
        if ([] === $payload['photos']) {
            $this->outbox->enqueue(new PruneMarketplacePhotos($fiche->idString()));
        }
    }
}
