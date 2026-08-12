<?php

declare(strict_types=1);

namespace App\Etl\EventSubscriber;

use App\Etl\Entity\FicheMarketplaceSync;
use App\Etl\Message\PruneMarketplacePhotos;
use App\Etl\Message\RemoveFicheFromMarketplace;
use App\Etl\Message\SyncFicheMarketplace;
use App\Etl\Repository\FicheMarketplaceSyncRepository;
use App\Pim\Entity\Fiche;
use App\Pim\Repository\FicheRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Uid\Ulid;

/**
 * L'échec est enregistré hors de la transaction du handler (rollback) : après
 * la dernière relance Messenger, la fiche est marquée en erreur pour être
 * reprise par `app:marketplace:sync --failed`.
 */
final readonly class MarketplaceSyncFailureSubscriber
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private FicheRepository $fiches,
        private FicheMarketplaceSyncRepository $tracking,
    ) {
    }

    #[AsEventListener]
    public function __invoke(WorkerMessageFailedEvent $event): void
    {
        if ($event->willRetry()) {
            return;
        }
        $message = $event->getEnvelope()->getMessage();
        if (
            !$message instanceof SyncFicheMarketplace
            && !$message instanceof RemoveFicheFromMarketplace
            && !$message instanceof PruneMarketplacePhotos
        ) {
            return;
        }
        $ficheId = Ulid::fromString($message->ficheId);
        $tracked = $this->tracking->forFiche($ficheId);
        if (null === $tracked) {
            $fiche = $this->fiches->find($ficheId);
            if (!$fiche instanceof Fiche) {
                return;
            }
            $tracked = new FicheMarketplaceSync($ficheId, $fiche->code());
            $this->entityManager->persist($tracked);
        }
        $tracked->recordFailure($event->getThrowable()->getMessage());
        $this->entityManager->flush();
    }
}
