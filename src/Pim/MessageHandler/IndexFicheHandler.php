<?php

declare(strict_types=1);

namespace App\Pim\MessageHandler;

use App\Etl\Service\MarketplaceSyncScheduler;
use App\Pim\Entity\Fiche;
use App\Pim\Message\IndexFiche;
use App\Pim\Repository\FicheRepository;
use App\Pim\Service\FicheSearchIndexer;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Ulid;

#[AsMessageHandler]
final readonly class IndexFicheHandler
{
    public function __construct(
        private FicheRepository $repository,
        private FicheSearchIndexer $indexer,
        private MarketplaceSyncScheduler $marketplaceScheduler,
    ) {
    }

    public function __invoke(IndexFiche $message): void
    {
        $fiche = $this->repository->find(Ulid::fromString($message->ficheId));
        if ($fiche instanceof Fiche) {
            $this->indexer->index($fiche);
            // Toute mutation de fiche converge ici : point unique de décision
            // de la diffusion marketplace (envoi ou dépublication).
            $this->marketplaceScheduler->schedule($fiche);
        }
    }
}
