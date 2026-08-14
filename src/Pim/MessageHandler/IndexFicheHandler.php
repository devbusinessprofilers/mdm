<?php

declare(strict_types=1);

namespace App\Pim\MessageHandler;

use App\Etl\Service\MarketplaceSyncScheduler;
use App\Pim\Entity\Fiche;
use App\Pim\Message\IndexFiche;
use App\Pim\Message\VerifierAdresseFiche;
use App\Pim\Repository\FicheRepository;
use App\Pim\Service\FicheSearchIndexer;
use App\Pim\Service\LocalisationBanVerifier;
use App\Shared\Outbox\OutboxPublisherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Ulid;

#[AsMessageHandler]
final readonly class IndexFicheHandler
{
    public function __construct(
        private FicheRepository $repository,
        private FicheSearchIndexer $indexer,
        private MarketplaceSyncScheduler $marketplaceScheduler,
        private OutboxPublisherInterface $outbox,
    ) {
    }

    public function __invoke(IndexFiche $message): void
    {
        $fiche = $this->repository->find(Ulid::fromString($message->ficheId));
        if ($fiche instanceof Fiche) {
            // Adresse française créée ou modifiée depuis le dernier passage
            // BAN (empreintes différentes) : vérification au fil de l'eau.
            // Enfilé avant index(), dont le flush persiste aussi l'outbox.
            $localisation = $fiche->localisation();
            if (null !== $localisation
                && LocalisationBanVerifier::estFrancaise($localisation)
                && null !== $localisation->addressFingerprint()
                && $localisation->addressFingerprint() !== $localisation->banFingerprint()) {
                $this->outbox->enqueue(new VerifierAdresseFiche($fiche->idString()));
            }
            $this->indexer->index($fiche);
            // Toute mutation de fiche converge ici : point unique de décision
            // de la diffusion marketplace (envoi ou dépublication).
            $this->marketplaceScheduler->schedule($fiche);
        }
    }
}
