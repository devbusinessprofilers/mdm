<?php

declare(strict_types=1);

namespace App\Pim\Completeness;

use App\Dam\Enum\MediaKind;
use App\Dam\Enum\MediaStatus;
use App\Dam\Repository\MediaAssetRepository;

final readonly class DoctrineCompletenessMediaAssetLookup implements CompletenessMediaAssetLookupInterface
{
    public function __construct(private MediaAssetRepository $assets)
    {
    }

    public function processedImageIds(array $assetIds): array
    {
        $eligible = [];
        foreach ($this->assets->findByStringIds(array_values(array_unique($assetIds))) as $asset) {
            if (MediaKind::Image === $asset->kind() && MediaStatus::Processed === $asset->status()) {
                $eligible[] = $asset->id();
            }
        }

        return $eligible;
    }
}
