<?php

declare(strict_types=1);

namespace App\Dam\MessageHandler;

use App\Dam\Message\RegenerateMedia;
use App\Dam\Repository\MediaAssetRepository;
use App\Dam\Service\MediaProcessingService;
use App\Dam\Service\MediaAnalysisService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

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
