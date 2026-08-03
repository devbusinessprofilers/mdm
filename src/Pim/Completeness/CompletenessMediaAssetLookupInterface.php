<?php

declare(strict_types=1);

namespace App\Pim\Completeness;

interface CompletenessMediaAssetLookupInterface
{
    /** @param list<string> $assetIds
     *  @return list<string>
     */
    public function processedImageIds(array $assetIds): array;
}
