<?php

declare(strict_types=1);

namespace App\Shared\Message;

final readonly class MediaUploaded
{
    /**
     * @param list<string> $expectedRenditions
     */
    public function __construct(
        public string $mediaId,
        public string $storageKey,
        public string $checksum,
        public array $expectedRenditions,
    ) {
    }
}
