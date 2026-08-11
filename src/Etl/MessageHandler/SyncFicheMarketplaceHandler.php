<?php

declare(strict_types=1);

namespace App\Etl\MessageHandler;

use App\Etl\Entity\FicheMarketplaceSync;
use App\Etl\Message\SyncFicheMarketplace;
use App\Etl\Repository\FicheMarketplaceSyncRepository;
use App\Etl\Service\MarketplaceApiException;
use App\Etl\Service\MarketplaceClientInterface;
use App\Etl\Service\MarketplaceFichePayloadBuilder;
use App\Etl\Service\MarketplaceSyncScheduler;
use App\Pim\Entity\Fiche;
use App\Pim\Enum\StatutFiche;
use App\Pim\Repository\FicheRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
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
        private LoggerInterface $logger,
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
        try {
            $applied = $this->client->upsertFiche($fiche->code(), $payload);
        } catch (MarketplaceApiException $exception) {
            // Refus permanent (4xx) : relancer rejouerait le même échec, le
            // message part directement en failed et l'échec est enregistré
            // par MarketplaceSyncFailureSubscriber.
            if (!$exception->isRetryable()) {
                throw new UnrecoverableMessageHandlingException($exception->getMessage(), 0, $exception);
            }
            throw $exception;
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
    }
}
