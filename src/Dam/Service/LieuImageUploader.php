<?php

declare(strict_types=1);

namespace App\Dam\Service;

use App\Dam\Entity\MediaAsset;
use App\Pim\Entity\Lieu\Lieu;
use App\Shared\Service\ParametreProviderInterface;
use App\Shared\Service\PrivateObjectStorageInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Ulid;

final readonly class LieuImageUploader
{
    // Garde anti « gigapixel » : au-delà, la génération des rendus épuise la mémoire du worker.
    private const MAX_DIMENSION = 12000;
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private PrivateObjectStorageInterface $storage,
        private ParametreProviderInterface $parametres,
        private string $storagePrefix,
    ) {
    }

    public function upload(UploadedFile $file, Lieu $lieu): MediaAsset
    {
        $path = $file->getRealPath();
        if (false === $path || !is_file($path)) {
            throw new \DomainException("Le fichier téléversé n'est plus disponible.");
        }
        $maxMo = $this->parametres->int('dam.image_poids_max_mo');
        $size = $file->getSize();
        if (false === $size || $size > $maxMo * 1024 * 1024) {
            throw new \DomainException(sprintf('Une image de lieu ne peut pas dépasser %d Mo.', $maxMo));
        }
        $image = @getimagesize($path);
        if (!is_array($image)) {
            throw new \DomainException('Formats autorisés : PNG, JPG, JPEG et WEBP.');
        }
        $mimeType = $image['mime'];
        if (!isset(self::EXTENSIONS[$mimeType])) {
            throw new \DomainException('Formats autorisés : PNG, JPG, JPEG et WEBP.');
        }
        $minWidth = $this->parametres->int('dam.image_largeur_min');
        $minHeight = $this->parametres->int('dam.image_hauteur_min');
        if ($image[0] < $minWidth || $image[1] < $minHeight) {
            throw new \DomainException(sprintf('Les dimensions minimales sont de %d × %d pixels.', $minWidth, $minHeight));
        }
        if ($image[0] > self::MAX_DIMENSION || $image[1] > self::MAX_DIMENSION) {
            throw new \DomainException('Les dimensions maximales sont de 12000 × 12000 pixels.');
        }
        $checksum = hash_file('sha256', $path);
        if (false === $checksum) {
            throw new \RuntimeException("Impossible de calculer l'empreinte du média.");
        }
        $mediaId = new Ulid();
        $prefix = trim($this->storagePrefix, '/');
        $key =
            ('' === $prefix ? '' : $prefix.'/').
            'lieux/'.
            $lieu->id().
            '/'.
            $mediaId.
            '/original.'.
            self::EXTENSIONS[$mimeType];
        $stream = fopen($path, 'rb');
        if (false === $stream) {
            throw new \RuntimeException("Impossible d'ouvrir le média à téléverser.");
        }
        try {
            $this->storage->writeStream($key, $stream, [
                'visibility' => 'private',
                'ContentType' => $mimeType,
                'Metadata' => [
                    'media-id' => (string) $mediaId,
                    'lieu-id' => $lieu->id(),
                    'checksum-sha256' => $checksum,
                ],
            ]);
        } finally {
            fclose($stream);
        }

        return new MediaAsset(
            $mediaId,
            $key,
            mb_substr($file->getClientOriginalName(), 0, 255),
            $mimeType,
            $size,
            $checksum,
        );
    }

    public function delete(MediaAsset $media): void
    {
        $this->storage->delete($media->originalStorageKey());
    }
}
