<?php

declare(strict_types=1);

namespace App\Dam\Service;

use App\Dam\Repository\MediaAssetRepository;
use App\Pim\Api\Dto\LieuDocumentResource;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Shared\Service\PrivateObjectStorageInterface;

final readonly class LieuDocumentPresenter
{
    public function __construct(
        private MediaAssetRepository $assets,
        private PublicMediaUrlGenerator $publicUrl,
        private PrivateObjectStorageInterface $privateStorage,
    ) {
    }

    public function resource(
        RessourceLieu $document,
        bool $withDownload = false,
    ): LieuDocumentResource {
        $asset = $this->assets->find($document->damAssetId());
        $download = null;
        if ($withDownload && null !== $asset) {
            $download = $this->privateStorage->temporaryUrl(
                $asset->originalStorageKey(),
                new \DateTimeImmutable('+10 minutes'),
            );
        }
        $access = $document->documentAccess();
        $status = $document->publicationStatus();

        return new LieuDocumentResource(
            id: $document->id(),
            version: $document->fiche()?->version() ?? 0,
            damAssetId: $document->damAssetId(),
            usage: $document->usage(),
            access: null === $access ? 'private' : $access->value,
            publicationStatus: null === $status ? 'private' : $status->value,
            title: $document->legende(),
            source: $document->source(),
            rightsGranted: $document->rightsGranted(),
            salleId: $document->salle()?->id()
                ?? $document->restaurantSalle()?->id(),
            filename: $asset?->originalFilename(),
            mimeType: $asset?->mimeType(),
            sizeBytes: $asset?->sizeBytes(),
            publicUrl: null === $document->publicStorageKey()
                ? null
                : $this->publicUrl->url($document->publicStorageKey()),
            downloadUrl: $download,
            keywords: $document->keywords(),
            rightsExpiresAt: $document->rightsExpiresAt()?->format('Y-m-d'),
            rightsValidity: $document->rightsValidity()->value,
        );
    }
}
