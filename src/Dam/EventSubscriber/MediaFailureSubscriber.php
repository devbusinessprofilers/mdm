<?php

declare(strict_types=1);

namespace App\Dam\EventSubscriber;

use App\Dam\Entity\MediaAsset;
use App\Dam\Message\DeleteMedia;
use App\Dam\Message\RegenerateMedia;
use App\Shared\Message\MediaUploaded;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Uid\Ulid;

#[AsEventListener]
final readonly class MediaFailureSubscriber
{
    public function __construct(
        private ManagerRegistry $registry,
        private LoggerInterface $logger,
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
        try {
            // L'échec du handler peut avoir fermé l'EntityManager (exception
            // Doctrine) : le réinitialiser avant de marquer le média.
            $manager = $this->registry->getManagerForClass(MediaAsset::class);
            if (!$manager instanceof EntityManagerInterface || !$manager->isOpen()) {
                $manager = $this->registry->resetManager();
            }
            $media = $manager->find(MediaAsset::class, $message->mediaId);
            if (null === $media) {
                return;
            }
            $media->markFailed($event->getThrowable()->getMessage());
            $manager->flush();
        } catch (\Throwable $error) {
            // Dernier recours : un subscriber d'échec ne doit jamais relancer.
            $this->logger->error('Impossible de marquer le média en échec.', [
                'media_id' => $message->mediaId,
                'exception' => $error,
            ]);
        }
    }
}
