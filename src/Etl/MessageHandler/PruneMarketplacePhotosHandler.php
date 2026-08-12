<?php

declare(strict_types=1);

namespace App\Etl\MessageHandler;

use App\Etl\Enum\MarketplaceSyncStatus;
use App\Etl\Message\PruneMarketplacePhotos;
use App\Etl\Message\RemoveFicheFromMarketplace;
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
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Uid\Ulid;

/**
 * Purge le snapshot marketplace des photos que le PIM ne détient plus, sans
 * toucher à l'éditorial publié (jamais d'ajout de photos de brouillon). Si le
 * snapshot passe sous les obligations photos de diffusion, la fiche est
 * dépubliée jusqu'à sa republication PIM.
 */
#[AsMessageHandler]
final readonly class PruneMarketplacePhotosHandler
{
    public function __construct(
        private FicheRepository $fiches,
        private FicheMarketplaceSyncRepository $tracking,
        private MarketplaceSyncScheduler $scheduler,
        private MarketplaceFichePayloadBuilder $payloadBuilder,
        private MarketplacePhotoPolicy $photoPolicy,
        private MarketplaceClientInterface $client,
        private OutboxPublisherInterface $outbox,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(PruneMarketplacePhotos $message): void
    {
        $ficheId = Ulid::fromString($message->ficheId);
        $tracked = $this->tracking->forFiche($ficheId);
        // Jamais envoyée : la marketplace ne peut détenir aucune photo. Une
        // fiche déjà dépubliée reste purgée : ses lignes photo aussi doivent
        // disparaître.
        if (null === $tracked) {
            return;
        }
        $fiche = $this->fiches->find($ficheId);
        // Fiche disparue ou archivée : le flux de dépublication fait foi.
        if (!$fiche instanceof Fiche || StatutFiche::Archivee === $fiche->status()) {
            return;
        }
        // Republiée conforme entre-temps : le Sync qui suit ce changement
        // remplace de toute façon la liste des photos. Une fiche publiée sans
        // photo PIM (transition legacy ou photos toutes supprimées) est
        // purgée : seuls les compteurs distants départagent les deux cas.
        if (
            StatutFiche::Publiee === $fiche->status()
            && $this->scheduler->diffusable($fiche)
            && $this->photoPolicy->appliesTo($fiche)
            && $this->photoPolicy->satisfiedByResources($fiche)
        ) {
            return;
        }
        $sequence = (string) new Ulid();
        try {
            $result = $this->client->pruneFichePhotos(
                $tracked->code(),
                $sequence,
                $this->payloadBuilder->photoLocations($fiche),
            );
        } catch (MarketplaceApiException $exception) {
            // Refus permanent (4xx) : relancer rejouerait le même échec, le
            // message part directement en failed et l'échec est enregistré
            // par MarketplaceSyncFailureSubscriber.
            if (!$exception->isRetryable()) {
                throw new UnrecoverableMessageHandlingException($exception->getMessage(), 0, $exception);
            }
            throw $exception;
        }
        // Fiche inconnue de la marketplace : rien à purger ni à décider.
        if (null === $result) {
            return;
        }
        // Conflit de séquence : la marketplace détient un état plus récent.
        if (false === $result) {
            $this->logger->notice('Purge des photos ignorée par la marketplace : elle détient déjà une séquence plus récente.', [
                'code' => $tracked->code(),
                'sequence' => $sequence,
            ]);

            return;
        }
        // Un échec de synchronisation en cours reste visible pour la reprise
        // `app:marketplace:sync --failed` : la séquence n'est consignée que
        // sur un suivi sain.
        if (MarketplaceSyncStatus::Synced === $tracked->status()) {
            $tracked->recordSynced($sequence);
        }
        // La marketplace détenait des photos PIM (`removed + remaining > 0`,
        // sinon la fiche est encore sur imagerie legacy) et il n'en reste pas
        // assez pour la diffusion : dépublication jusqu'à la republication.
        if (
            $result['removed'] + $result['remaining'] > 0
            && MarketplaceSyncStatus::Removed !== $tracked->status()
            && !$this->photoPolicy->satisfiedByRemote($fiche->type(), $result['remaining'], $result['principaleRemaining'])
        ) {
            $this->outbox->enqueue(new RemoveFicheFromMarketplace($fiche->idString()));
        }
    }
}
