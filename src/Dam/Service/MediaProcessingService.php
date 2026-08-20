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
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class MediaProcessingService
{
    public function __construct(
        private PrivateObjectStorageInterface $privateStorage,
        private PublicObjectStorageInterface $publicStorage,
        private ImageRenditionGenerator $generator,
        private PublicMediaUrlGenerator $urlGenerator,
        private RessourceLieuRepository $resources,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        #[Autowire(env: 'S3_PREFIX')]
        private string $storagePrefix,
    ) {
    }

    /**
     * @param string|null $originalPath copie locale de l'original, pour éviter
     *                                  un nouveau téléchargement S3 quand
     *                                  l'appelant l'a déjà récupéré
     *
     * @return array<string, string>
     */
    public function process(MediaAsset $media, ?string $originalPath = null): array
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
        $crop = $resource?->crop();
        $rotation = $resource?->rotation() ?? 0;
        $fingerprint = self::fingerprint($media, $crop, $rotation);
        $stream = null === $originalPath
            ? $this->privateStorage->readStream($media->sourceStorageKey())
            : fopen($originalPath, 'rb');
        if (false === $stream) {
            throw new \RuntimeException("Impossible de lire la copie locale de l'original.");
        }
        $staleKeys = [];
        try {
            foreach (
                $this->generator->generate($stream, $crop, $rotation) as $generated
            ) {
                $key = $this->renditionKey($media, $generated->name, $fingerprint);
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
                    $staleKey = $rendition->storageKey();
                    $rendition->refresh(
                        $key,
                        $generated->width,
                        $generated->height,
                        strlen($generated->contents),
                    );
                    // Les objets sont servis en cache immutable : une régénération
                    // (recadrage, rotation) produit une nouvelle clé, l'ancienne
                    // devient orpheline et doit être purgée.
                    if ($staleKey !== $key) {
                        $staleKeys[] = $staleKey;
                    }
                }
            }
        } finally {
            fclose($stream);
        }
        $media->markProcessed();
        $this->entityManager->flush();
        // Purge APRÈS le flush : en cas de rollback, les renditions en base
        // pointeraient sinon vers des objets déjà supprimés. Un échec de purge
        // ne laisse qu'un orphelin S3 bénin, il ne doit pas faire échouer le
        // traitement.
        foreach ($staleKeys as $staleKey) {
            try {
                $this->publicStorage->delete($staleKey);
            } catch (\Throwable $error) {
                $this->logger->warning('La purge d’un rendu public périmé a échoué.', [
                    'media_id' => $media->id(),
                    'storage_key' => $staleKey,
                    'exception' => $error,
                ]);
            }
        }

        return $this->urls($media);
    }

    /**
     * Clé publique d'un rendu : la variante est en tête de chemin et le nom de
     * fichier est identique pour toutes les variantes. Les sites diffuseurs
     * (marketplace) composent ainsi leurs URLs par simple concaténation
     * `base + variante + chemin relatif`, le chemin relatif étant le même
     * quelle que soit la variante.
     *
     * Forme : {prefix}/photos/{variante}/{segment}/{ficheId}/{assetId}-{empreinte}.webp
     */
    private function renditionKey(
        MediaAsset $media,
        string $variant,
        string $fingerprint,
    ): string {
        $prefix = trim($this->storagePrefix, '/');
        $dir = \dirname($media->originalStorageKey());
        if ('' !== $prefix && str_starts_with($dir, $prefix.'/')) {
            $dir = substr($dir, strlen($prefix) + 1);
        }
        // La clé d'origine se termine par le dossier de l'asset : le nom de
        // fichier reprenant déjà l'identifiant, ce dernier segment est retiré.
        $dir = \dirname($dir);
        $dir = in_array($dir, ['.', '/', ''], true) ? '' : $dir.'/';

        return ('' === $prefix ? '' : $prefix.'/').
            'photos/'.
            $variant.
            '/'.
            $dir.
            $media->id().
            '-'.
            $fingerprint.
            '.webp';
    }

    /**
     * @param array{x: int, y: int, width: int, height: int}|null $crop
     */
    private static function fingerprint(
        MediaAsset $media,
        ?array $crop,
        int $rotation,
    ): string {
        return substr(
            sha1($media->sourceChecksum().'|'.json_encode($crop).'|'.$rotation),
            0,
            8,
        );
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
