<?php

declare(strict_types=1);

namespace App\Dam\Service;

use App\Dam\Entity\MediaAsset;
use App\Dam\Repository\MediaAssetRepository;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Service\PhotoPrincipale;
use App\Pim\Service\PhotoUsageCatalog;
use App\Shared\Service\PrivateObjectStorageInterface;
use League\Flysystem\FilesystemException;

final readonly class FichePhotoPresenter
{
    public function __construct(
        private MediaAssetRepository $assets,
        private PrivateObjectStorageInterface $privateStorage,
        private PublicMediaUrlGenerator $publicUrl,
    ) {
    }

    /** @return list<array{resource:RessourceLieu,asset:MediaAsset|null,url:string|null,thumbnail_url:string|null,variants:list<array{name:string,width:int,height:int,url:string|null}>,usage_label:string}> */
    public function photos(Fiche $fiche): array
    {
        // Ordre canonique : la première photo est la principale.
        $resources = PhotoPrincipale::photosTriees($fiche->resources());
        $assets = [];
        foreach (
            $this->assets->findByStringIds(
                array_map(
                    static fn (RessourceLieu $r): string => $r->damAssetId(),
                    $resources,
                ),
            ) as $asset
        ) {
            $assets[$asset->id()] = $asset;
        }
        $result = [];
        foreach ($resources as $resource) {
            $asset = $assets[$resource->damAssetId()] ?? null;
            $url = $this->rendition($asset, 'large');
            $thumb = $this->rendition($asset, 'small');
            if (null === $url && $asset instanceof MediaAsset) {
                try {
                    $url = $this->privateStorage->temporaryUrl(
                        $asset->originalStorageKey(),
                        new \DateTimeImmutable('+10 minutes'),
                    );
                    $thumb = $url;
                } catch (FilesystemException) {
                }
            }
            $variants = [];
            foreach (ImageVariantRegistry::all() as $name => $size) {
                $variants[] = [
                    'name' => $name,
                    'width' => $size['width'],
                    'height' => $size['height'],
                    'url' => $this->rendition($asset, $name),
                ];
            }
            $result[] = [
                'resource' => $resource,
                'asset' => $asset,
                'url' => $url,
                'thumbnail_url' => $thumb ?? $url,
                'variants' => $variants,
                'usage_label' => PhotoUsageCatalog::label($resource->usage()),
            ];
        }

        return $result;
    }

    private function rendition(?MediaAsset $asset, string $name): ?string
    {
        $r = $asset?->rendition($name);

        return null === $r ? null : $this->publicUrl->url($r->storageKey());
    }
}
