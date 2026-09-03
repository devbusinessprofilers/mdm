<?php

declare(strict_types=1);

namespace App\Dam\MessageHandler;

use App\Dam\Message\UnpublishDocument;
use App\Pim\Repository\RessourceLieuRepository;
use App\Shared\Service\PublicObjectStorageInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UnpublishDocumentHandler
{
    public function __construct(
        private RessourceLieuRepository $resources,
        private PublicObjectStorageInterface $publicStorage,
    ) {
    }

    public function __invoke(UnpublishDocument $message): void
    {
        if ($this->publicStorage->exists($message->publicStorageKey)) {
            $this->publicStorage->delete($message->publicStorageKey);
        }
        $resource = $this->resources->find($message->resourceId);
        if (
            null !== $resource
            && $resource->publicStorageKey() === $message->publicStorageKey
        ) {
            $resource->confirmUnpublication();
        }
    }
}
