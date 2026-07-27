<?php

declare(strict_types=1);

namespace App\Shared\Message;

final readonly class MediaProcessed
{
    /**
     * @param array<string, string> $renditionUrls
     * @param list<string>          $tags
     */
    public function __construct(
        public string $mediaId,
        public array $renditionUrls,
        public array $tags,
        public string $status,
        public ?string $duplicateOf = null,
    ) {
    }
}
