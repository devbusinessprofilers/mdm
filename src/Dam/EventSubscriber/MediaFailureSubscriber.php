<?php

declare(strict_types=1);

namespace App\Dam\EventSubscriber;

use App\Dam\Entity\MediaAsset;
use App\Dam\Message\DeleteMedia;
use App\Dam\Message\RegenerateMedia;
use App\Shared\Message\MediaUploaded;
use App\Shared\Messenger\AbstractWorkerFailureListener;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Uid\Ulid;

/** Un traitement média qui a épuisé ses relances laisse l'asset en `failed`, avec le message d'erreur. */
#[AsEventListener]
final readonly class MediaFailureSubscriber extends AbstractWorkerFailureListener
{
    protected function concerne(object $message): bool
    {
        return ($message instanceof MediaUploaded || $message instanceof RegenerateMedia || $message instanceof DeleteMedia)
            && Ulid::isValid($message->mediaId);
    }

    protected function marquer(EntityManagerInterface $manager, object $message, WorkerMessageFailedEvent $event): void
    {
        /** @var MediaUploaded|RegenerateMedia|DeleteMedia $message */
        $media = $manager->find(MediaAsset::class, $message->mediaId);
        if ($media instanceof MediaAsset) {
            $media->markFailed($event->getThrowable()->getMessage());
        }
    }
}
