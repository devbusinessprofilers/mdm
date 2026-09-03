<?php

declare(strict_types=1);

namespace App\Shared\Service;

use Aws\S3\S3Client;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;

final readonly class OvhS3ObjectStorage implements PrivateObjectStorageInterface, PublicObjectStorageInterface
{
    private function __construct(private FilesystemOperator $filesystem)
    {
    }

    public static function create(string $endpoint, string $region, string $bucket, string $accessKey, string $secretKey,
    ): self {
        $configuration = [
            'version' => 'latest',
            'region' => $region,
            'endpoint' => rtrim($endpoint, '/'),
            'use_path_style_endpoint' => false,
        ];
        if ('' !== $accessKey && '' !== $secretKey) {
            $configuration['credentials'] = ['key' => $accessKey, 'secret' => $secretKey];
        }

        $client = new S3Client($configuration);

        return new self(new Filesystem(new AwsS3V3Adapter($client, $bucket)));
    }

    public function write(string $key, string $contents, array $options = []): void
    {
        $this->filesystem->write($key, $contents, $options);
    }

    public function writeStream(string $key, mixed $stream, array $options = []): void
    {
        if (!is_resource($stream)) {
            throw new \InvalidArgumentException('Le contenu à stocker doit être un flux ouvert.');
        }

        $this->filesystem->writeStream($key, $stream, $options);
    }

    public function read(string $key): string
    {
        return $this->filesystem->read($key);
    }

    public function readStream(string $key): mixed
    {
        return $this->filesystem->readStream($key);
    }

    public function exists(string $key): bool
    {
        return $this->filesystem->fileExists($key);
    }

    public function temporaryUrl(string $key, \DateTimeInterface $expiresAt): string
    {
        return $this->filesystem->temporaryUrl($key, $expiresAt);
    }

    public function delete(string $key): void
    {
        $this->filesystem->delete($key);
    }

    public function deleteDirectory(string $prefix): void
    {
        $prefix = trim($prefix, '/');

        if ('' === $prefix || '.' === $prefix) {
            throw new \InvalidArgumentException('Le préfixe de stockage à supprimer doit être non vide.');
        }

        $this->filesystem->deleteDirectory($prefix);
    }
}
