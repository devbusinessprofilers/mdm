<?php

declare(strict_types=1);

namespace App\Etl\EventSubscriber;

use App\Etl\Entity\FicheMarketplaceSync;
use App\Etl\Message\PruneMarketplacePhotos;
use App\Etl\Message\RemoveFicheFromMarketplace;
use App\Etl\Message\SyncFicheMarketplace;
use App\Etl\Repository\FicheMarketplaceSyncRepository;
use App\Pim\Entity\Fiche;
use App\Shared\Messenger\AbstractWorkerFailureListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Uid\Ulid;

/**
 * L'échec est enregistré hors de la transaction du handler (rollback) : après
 * la dernière relance Messenger, la fiche est marquée en erreur pour être
 * reprise par `app:marketplace:sync --failed`.
 */
#[AsEventListener]
final readonly class MarketplaceSyncFailureSubscriber extends AbstractWorkerFailureListener
{
    public function __construct(
        ManagerRegistry $registry,
        LoggerInterface $logger,
        private FicheMarketplaceSyncRepository $tracking,
    ) {
        parent::__construct($registry, $logger);
    }

    protected function concerne(object $message): bool
    {
        return ($message instanceof SyncFicheMarketplace || $message instanceof RemoveFicheFromMarketplace || $message instanceof PruneMarketplacePhotos)
            && Ulid::isValid($message->ficheId);
    }

    protected function marquer(EntityManagerInterface $manager, object $message, WorkerMessageFailedEvent $event): void
    {
        /** @var SyncFicheMarketplace|RemoveFicheFromMarketplace|PruneMarketplacePhotos $message */
        $ficheId = Ulid::fromString($message->ficheId);
        $tracked = $this->tracking->forFiche($ficheId);
        if (null === $tracked) {
            $fiche = $manager->find(Fiche::class, $ficheId);
            if (!$fiche instanceof Fiche) {
                return;
            }
            $tracked = new FicheMarketplaceSync($ficheId, $fiche->code());
            $manager->persist($tracked);
        }
        $tracked->recordFailure($event->getThrowable()->getMessage());
    }
}
