<?php

declare(strict_types=1);

namespace App\Shared\Service;

interface ObjectStorageInterface
{
    /**
     * @param array<string, mixed> $options
     */
    public function write(string $key, string $contents, array $options = []): void;

    /**
     * @param resource             $stream
     * @param array<string, mixed> $options
     */
    public function writeStream(string $key, mixed $stream, array $options = []): void;

    public function read(string $key): string;

    /** @return resource */
    public function readStream(string $key): mixed;

    public function exists(string $key): bool;

    public function temporaryUrl(string $key, \DateTimeInterface $expiresAt): string;

    public function delete(string $key): void;

    /**
     * Supprime tous les objets situés sous un préfixe non vide.
     */
    public function deleteDirectory(string $prefix): void;
}
