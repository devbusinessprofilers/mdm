<?php

declare(strict_types=1);

namespace App\Pim\MessageHandler;

use App\Pim\Repository\RessourceLieuRepository;
use App\Shared\Message\MediaProcessed;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class MediaProcessedHandler
{
    public function __construct(
        private RessourceLieuRepository $resources,
    ) {
    }

    public function __invoke(MediaProcessed $message): void
    {
        $resource = $this->resources->findOneByMediaId($message->mediaId);
        if (null === $resource || null === $resource->fiche()) {
            return;
        }

        $resource->fiche()->markSystemChanged();
    }
}
