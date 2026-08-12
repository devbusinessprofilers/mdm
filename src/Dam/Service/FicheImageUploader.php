<?php

declare(strict_types=1);

namespace App\Dam\Service;

use App\Dam\Entity\MediaAsset;
use App\Pim\Entity\Fiche;
use App\Shared\Service\ParametreProviderInterface;
use App\Shared\Service\PrivateObjectStorageInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Ulid;

final readonly class FicheImageUploader
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

    public function upload(UploadedFile $file, Fiche $fiche): MediaAsset
    {
        $maxMo = $this->parametres->int('dam.image_poids_max_mo');
        $path = $file->getRealPath();
        $size = $file->getSize();
        $image = false === $path ? false : @getimagesize($path);
        if (
            false === $path
            || !is_file($path)
            || false === $size
            || $size > $maxMo * 1024 * 1024
            || !is_array($image)
            || !isset(self::EXTENSIONS[$image['mime']])
        ) {
            throw new \DomainException(sprintf('Image PNG, JPG ou WEBP de %d Mo maximum attendue.', $maxMo));
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
            throw new \RuntimeException('Empreinte média impossible.');
        }
        $id = new Ulid();
        $segment = match ($fiche->type()->value) {
            'activite' => 'activites',
            'restaurant' => 'restaurants',
            'service_evenementiel' => 'services',
            'traiteur' => 'traiteurs',
            default => 'lieux',
        };
        $prefix = trim($this->storagePrefix, '/');
        $key =
            ('' === $prefix ? '' : $prefix.'/').
            $segment.
            '/'.
            $fiche->idString().
            '/'.
            $id.
            '/original.'.
            self::EXTENSIONS[$image['mime']];
        $stream = fopen($path, 'rb');
        if (false === $stream) {
            throw new \RuntimeException('Ouverture du média impossible.');
        }
        try {
            $this->storage->writeStream($key, $stream, [
                'visibility' => 'private',
                'ContentType' => $image['mime'],
                'Metadata' => [
                    'media-id' => (string) $id,
                    'fiche-id' => $fiche->idString(),
                    'checksum-sha256' => $checksum,
                ],
            ]);
        } finally {
            fclose($stream);
        }

        return new MediaAsset(
            $id,
            $key,
            mb_substr($file->getClientOriginalName(), 0, 255),
            $image['mime'],
            $size,
            $checksum,
        );
    }

    public function delete(MediaAsset $asset): void
    {
        $this->storage->delete($asset->originalStorageKey());
    }
}
