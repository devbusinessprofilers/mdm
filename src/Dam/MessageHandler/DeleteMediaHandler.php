<?php

declare(strict_types=1);

namespace App\Dam\MessageHandler;

use App\Dam\Enum\MediaStatus;
use App\Dam\Message\DeleteMedia;
use App\Dam\Repository\MediaAssetRepository;
use App\Shared\Service\PrivateObjectStorageInterface;
use App\Shared\Service\PublicObjectStorageInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DeleteMediaHandler
{
    public function __construct(
        private MediaAssetRepository $mediaRepository,
        private PrivateObjectStorageInterface $privateStorage,
        private PublicObjectStorageInterface $publicStorage,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(DeleteMedia $message): void
    {
        $media = $this->mediaRepository->find($message->mediaId);
        if (null === $media || MediaStatus::Deleted === $media->status()) {
            return;
        }

        $media->markDeleting();
        foreach ($media->renditions() as $rendition) {
            $this->publicStorage->delete($rendition->storageKey());
        }
        $this->privateStorage->delete($media->originalStorageKey());
        $media->markDeleted();
        $this->entityManager->flush();
    }
}
