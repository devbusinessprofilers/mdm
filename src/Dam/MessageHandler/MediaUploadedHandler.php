<?php

declare(strict_types=1);

namespace App\Dam\MessageHandler;

use App\Dam\Repository\MediaAssetRepository;
use App\Dam\Service\MediaProcessingService;
use App\Shared\Message\MediaProcessed;
use App\Shared\Message\MediaUploaded;
use App\Shared\Outbox\OutboxPublisherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class MediaUploadedHandler
{
    public function __construct(
        private MediaAssetRepository $mediaRepository,
        private MediaProcessingService $processor,
        private OutboxPublisherInterface $outbox,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(MediaUploaded $message): void
    {
        $media = $this->mediaRepository->find($message->mediaId);
        if (null === $media) {
            return;
        }
        if ($media->originalStorageKey() !== $message->storageKey || $media->checksum() !== $message->checksum) {
            throw new \DomainException('Le contrat MediaUploaded ne correspond pas au média enregistré.');
        }

        $urls = $this->processor->process($media);
        $this->outbox->enqueue(new MediaProcessed($media->id(), $urls, [], $media->status()->value));
        $this->entityManager->flush();
    }
}
