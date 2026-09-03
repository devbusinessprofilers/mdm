<?php

declare(strict_types=1);

namespace App\Dam\MessageHandler;

use App\Dam\Entity\MediaAsset;
use App\Dam\Enum\MediaKind;
use App\Dam\Enum\MediaStatus;
use App\Dam\Repository\MediaAssetRepository;
use App\Dam\Service\MediaAnalysisService;
use App\Dam\Service\MediaProcessingService;
use App\Shared\Message\MediaProcessed;
use App\Shared\Message\MediaUploaded;
use App\Shared\Outbox\OutboxPublisherInterface;
use App\Shared\Service\ParametreProviderInterface;
use App\Shared\Service\PrivateObjectStorageInterface;
use App\Vision\Entity\ImageRecognition;
use App\Vision\Service\ImageRecognitionManager;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[WithMonologChannel('vision')]
#[AsMessageHandler]
final readonly class MediaUploadedHandler
{
    public function __construct(
        private MediaAssetRepository $mediaRepository,
        private MediaProcessingService $processor,
        private MediaAnalysisService $analysis,
        private PrivateObjectStorageInterface $privateStorage,
        private OutboxPublisherInterface $outbox,
        private ImageRecognitionManager $recognitions,
        private ParametreProviderInterface $parametres,
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
        // Reconnaissance IA au fil de l'eau : après les renditions (l'analyse
        // lit l'URL publique large), non bloquante comme l'analyse pHash.
        // Paramètres lus à l'usage : les surcharges /admin s'appliquent sans
        // redémarrage des workers.
        if (
            MediaKind::Image === $media->kind()
            && MediaStatus::Processed === $media->status()
            && $this->parametres->bool('openai.actif')
            && $this->parametres->bool('openai.reco_auto_active')
        ) {
            try {
                $this->recognitions->scheduleForMedia($media, ImageRecognition::CREATED_BY_AUTO);
            } catch (\Throwable $error) {
                $this->logger->warning('La mise en file de la reconnaissance IA du média a échoué.', [
                    'media_id' => $media->id(),
                    'exception' => $error,
                ]);
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
