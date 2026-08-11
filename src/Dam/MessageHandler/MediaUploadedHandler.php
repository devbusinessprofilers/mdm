<?php

declare(strict_types=1);

namespace App\Dam\MessageHandler;

use App\Dam\Entity\MediaAsset;
use App\Dam\Enum\MediaStatus;
use App\Dam\Repository\MediaAssetRepository;
use App\Dam\Service\MediaProcessingService;
use App\Dam\Service\MediaAnalysisService;
use App\Shared\Message\MediaProcessed;
use App\Shared\Message\MediaUploaded;
use App\Shared\Outbox\OutboxPublisherInterface;
use App\Shared\Service\PrivateObjectStorageInterface;
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
        private PrivateObjectStorageInterface $privateStorage,
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
        // Original téléchargé une seule fois : le traitement des variantes et
        // l'analyse pHash relisent la même copie locale au lieu de refaire
        // chacun un GET S3.
        $originalPath = $this->downloadOriginal($media);
        try {
            $urls = $this->processor->process($media, $originalPath);
            $duplicate = null;
            try {
                $duplicate = $this->analysis->analyze($media, $originalPath);
            } catch (\Throwable $error) {
                $this->logger->warning('L’analyse pHash non bloquante du média a échoué.', [
                    'media_id' => $media->id(),
                    'exception' => $error,
                ]);
            }
        } finally {
            if (null !== $originalPath) {
                @unlink($originalPath);
            }
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

    private function downloadOriginal(MediaAsset $media): ?string
    {
        if (in_array($media->status(), [MediaStatus::Deleting, MediaStatus::Deleted], true)) {
            // Traitement et analyse n'y toucheront pas : inutile de télécharger.
            return null;
        }
        $path = tempnam(sys_get_temp_dir(), 'dam-original-');
        if (false === $path) {
            throw new \RuntimeException('Impossible de créer la copie locale de l’original.');
        }
        try {
            $source = $this->privateStorage->readStream($media->originalStorageKey());
            try {
                $destination = fopen($path, 'wb');
                if (false === $destination) {
                    throw new \RuntimeException('Impossible d’écrire la copie locale de l’original.');
                }
                try {
                    stream_copy_to_stream($source, $destination);
                } finally {
                    fclose($destination);
                }
            } finally {
                fclose($source);
            }
        } catch (\Throwable $error) {
            @unlink($path);
            throw $error;
        }

        return $path;
    }
}
