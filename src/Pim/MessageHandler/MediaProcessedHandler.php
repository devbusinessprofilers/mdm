<?php

declare(strict_types=1);

namespace App\Pim\MessageHandler;

use App\Pim\Repository\RessourceLieuRepository;
use App\Shared\Message\MediaProcessed;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class MediaProcessedHandler
{
    public function __construct(private RessourceLieuRepository $resources, private EntityManagerInterface $entityManager)
    {
    }

    public function __invoke(MediaProcessed $message): void
    {
        $resource = $this->resources->findOneByMediaId($message->mediaId);
        if (null === $resource || null === $resource->lieu()) {
            return;
        }

        $resource->lieu()->fiche()->markSystemChanged();
        $this->entityManager->flush();
    }
}
