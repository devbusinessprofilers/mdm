<?php

declare(strict_types=1);

namespace App\Dam\MessageHandler;

use App\Dam\Message\RegenerateMedia;
use App\Dam\Repository\MediaAssetRepository;
use App\Dam\Service\MediaProcessingService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RegenerateMediaHandler
{
    public function __construct(private MediaAssetRepository $mediaRepository, private MediaProcessingService $processor)
    {
    }

    public function __invoke(RegenerateMedia $message): void
    {
        $media = $this->mediaRepository->find($message->mediaId);
        if (null !== $media) {
            $media->markProcessing();
            $this->processor->process($media);
        }
    }
}
