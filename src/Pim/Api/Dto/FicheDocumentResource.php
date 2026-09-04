<?php

declare(strict_types=1);

namespace App\Pim\Api\Dto;

use ApiPlatform\Metadata\ApiProperty;

/** Un document d'une fiche dans l'API externe, toutes gammes. */
final readonly class FicheDocumentResource
{
    public function __construct(
        #[ApiProperty(identifier: true)] public string $id,
        public int $version,
        public string $damAssetId,
        public string $usage,
        public string $access,
        public string $publicationStatus,
        public ?string $title,
        public ?string $source,
        public bool $rightsGranted,
        public ?string $salleId,
        public ?string $filename,
        public ?string $mimeType,
        public ?int $sizeBytes,
        public ?string $publicUrl,
        public ?string $downloadUrl = null,
        public ?string $keywords = null,
        public ?string $rightsExpiresAt = null,
        public string $rightsValidity = 'not_granted',
    ) {
    }
}
