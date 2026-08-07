<?php

declare(strict_types=1);

namespace App\Dam\MessageHandler;

use App\Dam\Repository\MediaAssetRepository;
use App\Dam\Service\MediaProcessingService;
use App\Dam\Service\MediaAnalysisService;
use App\Shared\Message\MediaProcessed;
use App\Shared\Message\MediaUploaded;
use App\Shared\Outbox\OutboxPublisherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class MediaUploadedHandler
{
    public function __construct(
        private MediaAssetRepository $mediaRepository,
        private MediaProcessingService $processor,
        private MediaAnalysisService $analysis,
        private OutboxPublisherInterface $outbox,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(MediaUploaded $message): void
    {
        $media = $this->mediaRepository->find($message->mediaId);
        if (null === $media) {
            return;
        }
        if (
            $media->originalStorageKey() !== $message->storageKey
            || $media->checksum() !== $message->checksum
        ) {
            // Message périmé (média ré-uploadé entre l'émission et la
            // consommation) : l'ignorer — un retry ne le fera jamais
            // correspondre et polluerait la queue failed.
            $this->logger->info('Message MediaUploaded périmé (média modifié depuis l’émission), ignoré.', [
                'media_id' => $message->mediaId,
                'expected_storage_key' => $message->storageKey,
                'actual_storage_key' => $media->originalStorageKey(),
            ]);

            return;
        }
        $urls = $this->processor->process($media);
        $duplicate = null;
        try {
            $duplicate = $this->analysis->analyze($media);
        } catch (\Throwable $error) {
            $this->logger->warning('L’analyse pHash non bloquante du média a échoué.', [
                'media_id' => $media->id(),
                'exception' => $error,
            ]);
        }
        $this->outbox->enqueue(
            new MediaProcessed(
                $media->id(),
                $urls,
                [],
                $media->status()->value,
                $duplicate?->id(),
            ),
        );
        $this->entityManager->flush();
    }
}
