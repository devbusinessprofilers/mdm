<?php

declare(strict_types=1);

namespace App\Pim\Api\Dto;

/** Une photo d'une fiche dans l'API externe, toutes gammes. */
final readonly class FicheMediaResource
{
    /**
     * @param array{x: int, y: int, width: int, height: int}|null                  $crop
     * @param list<array{name: string, width: int, height: int, url: string|null}> $variants
     */
    public function __construct(
        public string $id,
        public int $version,
        public string $damAssetId,
        public string $usage,
        public ?string $legende,
        public int $position,
        public bool $rightsGranted,
        public ?string $source,
        public ?array $crop,
        public int $rotation,
        public ?string $status,
        public ?string $url,
        public array $variants,
        public ?string $keywords = null,
        public ?string $rightsExpiresAt = null,
        public string $rightsValidity = 'not_granted',
    ) {
    }
}
