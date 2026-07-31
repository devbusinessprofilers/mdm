<?php

declare(strict_types=1);

namespace App\Dam\EventSubscriber;

use App\Dam\Message\DeleteMedia;
use App\Dam\Message\RegenerateMedia;
use App\Dam\Repository\MediaAssetRepository;
use App\Shared\Message\MediaUploaded;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Uid\Ulid;

#[AsEventListener]
final readonly class MediaFailureSubscriber
{
    public function __construct(
        private MediaAssetRepository $repository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(WorkerMessageFailedEvent $event): void
    {
        if ($event->willRetry()) {
            return;
        }
        $message = $event->getEnvelope()->getMessage();
        if (
            !($message instanceof MediaUploaded)
            && !($message instanceof RegenerateMedia)
            && !($message instanceof DeleteMedia)
        ) {
            return;
        }
        if (!Ulid::isValid($message->mediaId)) {
            return;
        }
        $media = $this->repository->find($message->mediaId);
        if (null === $media) {
            return;
        }
        $media->markFailed($event->getThrowable()->getMessage());
        $this->entityManager->flush();
    }
}
