<?php

declare(strict_types=1);

namespace App\Ocr\Service;

interface DocumentExtractionProviderInterface
{
    public function upload(string $path, string $filename): string;

    /**
     * @param list<array<string, mixed>> $fields
     *
     * @return array<string, mixed>
     */
    public function extract(string $fileId, array $fields): array;

    public function delete(string $fileId): void;
}
