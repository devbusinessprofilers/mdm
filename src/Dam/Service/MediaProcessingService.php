<?php

declare(strict_types=1);

namespace App\Dam\Service;

use App\Dam\Entity\MediaAsset;
use App\Dam\Entity\MediaRendition;
use App\Dam\Enum\MediaStatus;
use App\Pim\Repository\RessourceLieuRepository;
use App\Shared\Service\PrivateObjectStorageInterface;
use App\Shared\Service\PublicObjectStorageInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class MediaProcessingService
{
    public function __construct(
        private PrivateObjectStorageInterface $privateStorage,
        private PublicObjectStorageInterface $publicStorage,
        private ImageRenditionGenerator $generator,
        private PublicMediaUrlGenerator $urlGenerator,
        private RessourceLieuRepository $resources,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /** @return array<string, string> */
    public function process(MediaAsset $media): array
    {
        if (
            MediaStatus::Deleted === $media->status()
            || MediaStatus::Deleting === $media->status()
        ) {
            return [];
        }
        if (
            MediaStatus::Processed === $media->status()
            && $this->hasEveryRendition($media)
        ) {
            return $this->urls($media);
        }
        $media->markProcessing();
        $resource = $this->resources->findOneByMediaId($media->id());
        $stream = $this->privateStorage->readStream(
            $media->originalStorageKey(),
        );
        try {
            foreach (
                $this->generator->generate(
                    $stream,
                    $resource?->crop(),
                    $resource?->rotation() ?? 0,
                ) as $generated
            ) {
                $key = preg_replace(
                    '#/original\.[^/]+$#',
                    '/renditions/'.$generated->name.'.webp',
                    $media->originalStorageKey(),
                );
                if (!is_string($key) || $key === $media->originalStorageKey()) {
                    throw new \RuntimeException("La clé du rendu n'a pas pu être construite.");
                }
                $this->publicStorage->write($key, $generated->contents, [
                    'visibility' => 'public',
                    'ContentType' => 'image/webp',
                    'CacheControl' => 'public, max-age=31536000, immutable',
                ]);
                $rendition = $media->rendition($generated->name);
                if (null === $rendition) {
                    $rendition = new MediaRendition(
                        $media,
                        $generated->name,
                        $key,
                        $generated->width,
                        $generated->height,
                        strlen($generated->contents),
                    );
                    $media->addRendition($rendition);
                } else {
                    $rendition->refresh(
                        $key,
                        $generated->width,
                        $generated->height,
                        strlen($generated->contents),
                    );
                }
            }
        } finally {
            fclose($stream);
        }
        $media->markProcessed();
        $this->entityManager->flush();

        return $this->urls($media);
    }

    private function hasEveryRendition(MediaAsset $media): bool
    {
        foreach (ImageVariantRegistry::names() as $name) {
            if (null === $media->rendition($name)) {
                return false;
            }
        }

        return true;
    }

    /** @return array<string, string> */
    private function urls(MediaAsset $media): array
    {
        $urls = [];
        foreach ($media->renditions() as $rendition) {
            $urls[$rendition->name()] = $this->urlGenerator->url(
                $rendition->storageKey(),
            );
        }

        return $urls;
    }
}
