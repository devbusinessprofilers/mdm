<?php

declare(strict_types=1);

namespace App\Dam\Service;

use App\Dam\Enum\RightsValidityStatus;
use App\Dam\Repository\MediaAssetRepository;
use App\Dam\Repository\MediaDuplicateAlertRepository;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Repository\RessourceLieuRepository;

final readonly class DamAnomalyCounter
{
    public function __construct(
        private MediaDuplicateAlertRepository $alerts,
        private RessourceLieuRepository $resources,
        private MediaAssetRepository $assets,
    ) {}

    /** @return array{duplicates: int, rights_missing: int, rights_expiring: int, rights_expired: int, failed: int, total: int} */
    public function forFiche(Fiche $fiche): array
    {
        $duplicates = $this->alerts->countPendingForFiche($fiche->idString());
        $missing = $this->resources->countByRightsStatusForFiche($fiche, RightsValidityStatus::NotGranted);
        $expiring = $this->resources->countByRightsStatusForFiche($fiche, RightsValidityStatus::Expiring);
        $expired = $this->resources->countByRightsStatusForFiche($fiche, RightsValidityStatus::Expired);
        $failed = $this->assets->countFailedByStringIds(array_values(array_map(
            static fn (RessourceLieu $resource): string => $resource->damAssetId(),
            $fiche->resources()->toArray(),
        )));

        return [
            'duplicates' => $duplicates,
            'rights_missing' => $missing,
            'rights_expiring' => $expiring,
            'rights_expired' => $expired,
            'failed' => $failed,
            'total' => $duplicates + $missing + $expiring + $expired + $failed,
        ];
    }
}
