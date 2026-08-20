<?php

declare(strict_types=1);

namespace App\Vision\MessageHandler;

use App\Dam\Enum\MediaStatus;
use App\Dam\Repository\MediaAssetRepository;
use App\Dam\Service\MediaProcessingService;
use App\Pim\Message\IndexFiche;
use App\Pim\Repository\RessourceLieuRepository;
use App\Shared\Outbox\OutboxPublisherInterface;
use App\Vision\Message\ApplyImageEnhancement;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Régénère les renditions d'un média depuis sa source active (retouche
 * acceptée, ou original après retour arrière), puis re-pousse la fiche vers
 * la marketplace — l'ordre est garanti par ce handler unique.
 */
#[AsMessageHandler]
final readonly class ApplyImageEnhancementHandler
{
    public function __construct(
        private MediaAssetRepository $mediaRepository,
        private MediaProcessingService $processor,
        private RessourceLieuRepository $resources,
        private OutboxPublisherInterface $outbox,
    ) {
    }

    public function __invoke(ApplyImageEnhancement $message): void
    {
        $media = $this->mediaRepository->find($message->mediaId);
        if (null === $media || in_array($media->status(), [MediaStatus::Deleting, MediaStatus::Deleted], true)) {
            return;
        }
        // Sans ce passage par Processing, process() court-circuite : un média
        // déjà Processed avec toutes ses renditions n'est pas régénéré.
        $media->markProcessing();
        $this->processor->process($media);
        $resource = $this->resources->findOneByMediaId($media->id());
        $fiche = $resource?->fiche();
        if (null !== $fiche) {
            $this->outbox->enqueue(new IndexFiche($fiche->idString()));
        }
    }
}
