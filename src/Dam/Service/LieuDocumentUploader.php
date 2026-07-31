<?php

declare(strict_types=1);

namespace App\Dam\Service;

use App\Dam\Entity\MediaAsset;
use App\Dam\Enum\DocumentUsage;
use App\Dam\Enum\MediaKind;
use App\Pim\Entity\Lieu\Lieu;
use App\Shared\Service\PrivateObjectStorageInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Uid\Ulid;

final readonly class LieuDocumentUploader
{
    private const EXTENSIONS = [
        'application/pdf' => ['pdf'],
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
    ];

    public function __construct(
        private PrivateObjectStorageInterface $storage,
        private string $storagePrefix,
    ) {
    }

    public function upload(
        UploadedFile $file,
        Lieu $lieu,
        DocumentUsage $usage,
    ): MediaAsset {
        $path = $file->getRealPath();
        if (false === $path || !is_file($path)) {
            throw new \DomainException("Le fichier téléversé n'est plus disponible.");
        }
        $size = filesize($path);
        if (false === $size || $size > $usage->maximumBytes()) {
            throw new DocumentUploadException(413, sprintf('Ce document ne peut pas dépasser %d Mo.', intdiv($usage->maximumBytes(), 1024 * 1024)));
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $extension = strtolower(
            pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION),
        );
        if (
            !is_string($mime)
            || !isset(self::EXTENSIONS[$mime])
            || !in_array($extension, self::EXTENSIONS[$mime], true)
        ) {
            throw new DocumentUploadException(415, "Le contenu réel et l'extension doivent correspondre à un PDF, JPEG ou PNG.");
        }
        $checksum = hash_file('sha256', $path);
        if (false === $checksum) {
            throw new \RuntimeException("Impossible de calculer l'empreinte du document.");
        }
        $id = new Ulid();
        $slug = (new AsciiSlugger())
            ->slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
            ->lower()
            ->toString();
        $filename = mb_substr(
            ('' === $slug ? 'document' : $slug).'.'.$extension,
            0,
            255,
        );
        $prefix = trim($this->storagePrefix, '/');
        $key =
            ('' === $prefix ? '' : $prefix.'/').
            'lieux/'.
            $lieu->id().
            '/documents/'.
            $id.
            '/'.
            $filename;
        $stream = fopen($path, 'rb');
        if (false === $stream) {
            throw new \RuntimeException("Impossible d'ouvrir le document.");
        }
        try {
            $this->storage->writeStream($key, $stream, [
                'visibility' => 'private',
                'ContentType' => $mime,
                'Metadata' => [
                    'media-id' => (string) $id,
                    'lieu-id' => $lieu->id(),
                    'checksum-sha256' => $checksum,
                ],
            ]);
        } finally {
            fclose($stream);
        }
        $asset = new MediaAsset(
            $id,
            $key,
            $filename,
            $mime,
            $size,
            $checksum,
            MediaKind::Document,
        );
        $asset->markProcessed();

        return $asset;
    }

    public function delete(MediaAsset $asset): void
    {
        $this->storage->delete($asset->originalStorageKey());
    }
}
