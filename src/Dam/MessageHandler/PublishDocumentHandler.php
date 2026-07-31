<?php

declare(strict_types=1);

namespace App\Dam\MessageHandler;

use App\Dam\Enum\DocumentPublicationStatus;
use App\Dam\Enum\MediaKind;
use App\Dam\Enum\MediaStatus;
use App\Dam\Message\PublishDocument;
use App\Dam\Repository\MediaAssetRepository;
use App\Pim\Repository\RessourceLieuRepository;
use App\Shared\Service\PrivateObjectStorageInterface;
use App\Shared\Service\PublicObjectStorageInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsMessageHandler]
final readonly class PublishDocumentHandler
{
    public function __construct(
        private RessourceLieuRepository $resources,
        private MediaAssetRepository $assets,
        private PrivateObjectStorageInterface $privateStorage,
        private PublicObjectStorageInterface $publicStorage,
        private EntityManagerInterface $entityManager,
        #[Autowire(env: 'S3_PREFIX')]
        private string $storagePrefix,
    ) {
    }

    public function __invoke(PublishDocument $message): void
    {
        $resource = $this->resources->find($message->resourceId);
        if (
            null === $resource
            || DocumentPublicationStatus::Publishing !==
                $resource->publicationStatus()
        ) {
            return;
        }
        $asset = $this->assets->find($resource->damAssetId());
        if (
            null === $asset
            || MediaKind::Document !== $asset->kind()
            || MediaStatus::Processed !== $asset->status()
        ) {
            throw new \DomainException('Le document DAM à publier est invalide.');
        }
        $fiche =
            $resource->fiche() ??
            throw new \DomainException('Le document n’est rattaché à aucune fiche.');
        $prefix = trim($this->storagePrefix, '/');
        $key =
            ('' === $prefix ? '' : $prefix.'/').
            'documents/'.
            $fiche->type()->value.
            '/'.
            $fiche->idString().
            '/'.
            $resource->id().
            '/'.
            $asset->checksum().
            '/'.
            $asset->originalFilename();
        if (!$this->publicStorage->exists($key)) {
            $stream = $this->privateStorage->readStream(
                $asset->originalStorageKey(),
            );
            try {
                $this->publicStorage->writeStream($key, $stream, [
                    'visibility' => 'public',
                    'ContentType' => $asset->mimeType(),
                ]);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        }
        $resource->confirmPublication($key);
        $this->entityManager->flush();
    }
}
