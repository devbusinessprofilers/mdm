<?php

declare(strict_types=1);

namespace App\Shared\Service;

interface ObjectStorageInterface
{
    /**
     * @param array<string, string> $metadata
     */
    public function write(string $key, string $contents, array $metadata = []): void;

    public function read(string $key): string;

    public function exists(string $key): bool;

    public function delete(string $key): void;
}
