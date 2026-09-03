<?php

declare(strict_types=1);

namespace App\Dam\MessageHandler;

use App\Dam\Enum\MediaStatus;
use App\Dam\Message\RegenerateMedia;
use App\Dam\Repository\MediaAssetRepository;
use App\Dam\Service\MediaAnalysisService;
use App\Dam\Service\MediaProcessingService;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[WithMonologChannel('vision')]
#[AsMessageHandler]
final readonly class RegenerateMediaHandler
{
    public function __construct(
        private MediaAssetRepository $mediaRepository,
        private MediaProcessingService $processor,
        private MediaAnalysisService $analysis,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(RegenerateMedia $message): void
    {
        $media = $this->mediaRepository->find($message->mediaId);
        if (null !== $media) {
            // Message en retard sur une suppression : ne pas repasser un média
            // supprimé en Processing (markProcessing n'a pas de garde et
            // contournerait le contrôle Deleted/Deleting de process()).
            if (MediaStatus::Deleting === $media->status() || MediaStatus::Deleted === $media->status()) {
                return;
            }
            // Forcé AVANT process() : sortir du statut Processed pour court-
            // circuiter son early-return "déjà traité" et régénérer vraiment.
            $media->markProcessing();
            $this->processor->process($media);
            try {
                $this->analysis->analyze($media);
            } catch (\Throwable $error) {
                $this->logger->warning('L’analyse pHash non bloquante du média régénéré a échoué.', [
                    'media_id' => $media->id(),
                    'exception' => $error,
                ]);
            }
        }
    }
}
