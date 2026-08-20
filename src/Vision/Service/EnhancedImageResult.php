<?php

declare(strict_types=1);

namespace App\Vision\Service;

final readonly class EnhancedImageResult
{
    /** @param array<string, mixed> $raw métadonnées du fournisseur, sans l'image encodée */
    public function __construct(
        public string $bytes,
        public string $mimeType,
        public array $raw,
    ) {
    }
}
