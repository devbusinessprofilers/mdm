<?php

declare(strict_types=1);

namespace App\Dam\Service;

use App\Dam\Entity\MediaAsset;
use App\Dam\Repository\MediaAssetRepository;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Service\PhotoPrincipale;
use App\Pim\Service\PhotoUsageCatalog;
use App\Shared\Service\PrivateObjectStorageInterface;
use League\Flysystem\FilesystemException;

final readonly class LieuPhotoPresenter
{
    public function __construct(
        private MediaAssetRepository $mediaRepository,
        private PrivateObjectStorageInterface $privateStorage,
        private PublicMediaUrlGenerator $publicUrl,
    ) {
    }

    /**
     * @return list<array{
     *     resource: RessourceLieu,
     *     asset: MediaAsset|null,
     *     url: string|null,
     *     thumbnail_url: string|null,
     *     variants: list<array{name: string, width: int, height: int, url: string|null}>,
     *     usage_label: string
     * }>
     */
    public function photos(Lieu $lieu): array
    {
        // Ordre canonique : la première photo est la principale.
        $resources = PhotoPrincipale::photosTriees($lieu->ressources());
        $assets = [];
        foreach (
            $this->mediaRepository->findByStringIds(
                array_map(
                    static fn (
                        RessourceLieu $resource,
                    ): string => $resource->damAssetId(),
                    $resources,
                ),
            ) as $asset
        ) {
            $assets[$asset->id()] = $asset;
        }
        $photos = [];
        foreach ($resources as $resource) {
            $asset = $assets[$resource->damAssetId()] ?? null;
            $url = $this->renditionUrl($asset, 'large');
            $thumbnail = $this->renditionUrl($asset, 'small');
            if (null === $url && $asset instanceof MediaAsset) {
                try {
                    $url = $this->privateStorage->temporaryUrl(
                        $asset->originalStorageKey(),
                        new \DateTimeImmutable('+10 minutes'),
                    );
                    $thumbnail = $url;
                } catch (FilesystemException) {
                    // A storage outage must not prevent the Lieu page from loading.
                }
            }
            $variants = [];
            foreach (ImageVariantRegistry::all() as $name => $dimensions) {
                $variants[] = [
                    'name' => $name,
                    'width' => $dimensions['width'],
                    'height' => $dimensions['height'],
                    'url' => $this->renditionUrl($asset, $name),
                ];
            }
            $photos[] = [
                'resource' => $resource,
                'asset' => $asset,
                'url' => $url,
                'thumbnail_url' => $thumbnail ?? $url,
                'variants' => $variants,
                'usage_label' => PhotoUsageCatalog::label($resource->usage()),
            ];
        }

        return $photos;
    }

    private function renditionUrl(?MediaAsset $asset, string $name): ?string
    {
        $rendition = $asset?->rendition($name);

        return null === $rendition
            ? null
            : $this->publicUrl->url($rendition->storageKey());
    }
}
