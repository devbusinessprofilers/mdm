<?php

declare(strict_types=1);

namespace App\Etl\MessageHandler;

use App\Etl\Enum\MarketplaceSyncStatus;
use App\Etl\Message\RemoveFicheFromMarketplace;
use App\Etl\Repository\FicheMarketplaceSyncRepository;
use App\Etl\Service\MarketplaceApiException;
use App\Etl\Service\MarketplaceClientInterface;
use App\Etl\Service\MarketplacePhotoPolicy;
use App\Etl\Service\MarketplaceSyncScheduler;
use App\Pim\Entity\Fiche;
use App\Pim\Enum\StatutFiche;
use App\Pim\Repository\FicheRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Uid\Ulid;

#[AsMessageHandler]
final readonly class RemoveFicheFromMarketplaceHandler
{
    public function __construct(
        private FicheRepository $fiches,
        private FicheMarketplaceSyncRepository $tracking,
        private MarketplaceSyncScheduler $scheduler,
        private MarketplacePhotoPolicy $photoPolicy,
        private MarketplaceClientInterface $client,
        private LoggerInterface $logger,
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
        // Rediffusée entre-temps : le Sync qui suit ce changement fera foi —
        // sauf si ses photos PIM ne suffisent plus à la diffusion, auquel cas
        // la dépublication reste due.
        if (
            $fiche instanceof Fiche
            && StatutFiche::Publiee === $fiche->status()
            && $this->scheduler->diffusable($fiche)
            && $this->photoPolicy->allowsPublication($fiche)
        ) {
            return;
        }
        $sequence = (string) new Ulid();
        try {
            $applied = $this->client->removeFiche($tracked->code(), $sequence);
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
        // récent, l'état local ne doit pas être marqué retiré avec une
        // séquence refusée.
        if (!$applied) {
            $this->logger->notice('Dépublication ignorée par la marketplace : elle détient déjà une séquence plus récente.', [
                'code' => $tracked->code(),
                'sequence' => $sequence,
            ]);

            return;
        }
        $tracked->recordRemoved($sequence);
    }
}
