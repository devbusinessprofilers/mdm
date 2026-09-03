<?php

declare(strict_types=1);

namespace App\Pim\Completeness;

use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Enum\NatureRessource;
use Symfony\Component\Uid\Ulid;

final readonly class CompletenessPhotoEligibilityResolver
{
    public function __construct(private CompletenessMediaAssetLookupInterface $assets)
    {
    }

    /**
     * @param array<string, object> $entities indexées par identifiant de fiche
     *
     * @return array<string, bool>
     */
    public function resolve(array $entities): array
    {
        $candidates = [];
        foreach ($entities as $ficheId => $entity) {
            if (!method_exists($entity, 'fiche')) {
                continue;
            }
            foreach ($entity->fiche()->resources() as $resource) {
                if (!$resource instanceof RessourceLieu
                    || NatureRessource::Photo !== $resource->nature()
                    || !$resource->rightsGranted()
                    || '' === $resource->damAssetId()) {
                    continue;
                }
                try {
                    $assetId = Ulid::fromString($resource->damAssetId());
                } catch (\InvalidArgumentException) {
                    continue;
                }
                $candidates[$ficheId][(string) $assetId] = $assetId;
            }
        }
        $result = array_fill_keys(array_keys($entities), false);
        if ([] === $candidates) {
            return $result;
        }
        $assetIds = [];
        foreach ($candidates as $ids) {
            $assetIds = [...$assetIds, ...array_keys($ids)];
        }
        $eligible = array_fill_keys($this->assets->processedImageIds(array_values(array_unique($assetIds))), true);
        foreach ($candidates as $ficheId => $ids) {
            foreach (array_keys($ids) as $assetId) {
                if (isset($eligible[$assetId])) {
                    $result[$ficheId] = true;
                    break;
                }
            }
        }

        return $result;
    }
}
