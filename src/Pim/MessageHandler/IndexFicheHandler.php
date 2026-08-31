<?php

declare(strict_types=1);

namespace App\Pim\MessageHandler;

use App\Etl\Service\MarketplaceSyncScheduler;
use App\Etl\Service\PhotoPublicationGuard;
use App\Etl\Service\SalesforceExportScheduler;
use App\Pim\Entity\Fiche;
use App\Pim\Message\AnalyzeFicheTexts;
use App\Pim\Message\IndexFiche;
use App\Pim\Message\VerifierAdresseFiche;
use App\Pim\Repository\FicheRepository;
use App\Pim\Service\FicheSearchIndexer;
use App\Pim\Service\GeocodeurAdresses;
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
        private SalesforceExportScheduler $salesforceScheduler,
        private PhotoPublicationGuard $photoGuard,
        private OutboxPublisherInterface $outbox,
        private GeocodeurAdresses $geocodeurs,
    ) {
    }

    public function __invoke(IndexFiche $message): void
    {
        $fiche = $this->repository->find(Ulid::fromString($message->ficheId));
        if ($fiche instanceof Fiche) {
            // Adresse créée ou modifiée depuis la dernière vérification
            // (empreintes différentes) et couverte par un géocodeur configuré
            // (BAN France, Geoapify étranger) : vérification au fil de l'eau.
            // Enfilé avant index(), dont le flush persiste aussi l'outbox.
            $localisation = $fiche->localisation();
            if (null !== $localisation && $this->geocodeurs->estVerifiable($localisation)) {
                $this->outbox->enqueue(new VerifierAdresseFiche($fiche->idString()));
            }
            // Invariant photos avant indexation et diffusion : une fiche
            // publiée qui ne satisfait plus les obligations (suppression de
            // photos sous le minimum) repasse en cours et quitte la
            // marketplace.
            $this->photoGuard->enforce($fiche);
            $this->indexer->index($fiche);
            // Point de convergence de toute mutation : on y planifie aussi la
            // détection de doublons de textes (mise à jour technique, sans
            // transition de workflow).
            $this->outbox->enqueue(new AnalyzeFicheTexts($fiche->idString()));
            // Toute mutation de fiche converge ici : point unique de décision
            // de la diffusion marketplace (envoi ou dépublication).
            $this->marketplaceScheduler->schedule($fiche);
            // Même point de convergence pour la synchro Salesforce (CSV e-mail,
            // système de transition) : la fiche est marquée « à synchroniser »,
            // l'envoi est différé et coalescé. L'import legacy n'enfile pas
            // IndexFiche, il n'est donc jamais concerné.
            $this->salesforceScheduler->schedule($fiche);
        }
    }
}
