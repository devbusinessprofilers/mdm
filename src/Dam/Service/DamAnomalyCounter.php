<?php

declare(strict_types=1);

namespace App\Dam\Service;

use App\Dam\Enum\DamAnomalyType;
use App\Dam\Enum\RightsValidityStatus;
use App\Dam\Repository\DamAnomalyRepository;
use App\Dam\Repository\MediaAssetRepository;
use App\Dam\Repository\MediaDuplicateAlertRepository;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Repository\RessourceLieuRepository;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final readonly class DamAnomalyCounter
{
    public function __construct(
        private MediaDuplicateAlertRepository $alerts,
        private RessourceLieuRepository $resources,
        private MediaAssetRepository $assets,
        private DamAnomalyRepository $anomalies,
        private CacheInterface $cache,
    ) {
    }

    /** Anomalies ouvertes tous sujets confondus — le badge « Qualité » de la navigation. */
    public function ouvertes(): int
    {
        // Le badge est rendu sur chaque page du back-office : un cache court
        // évite trois COUNT par requête, 60 s de retard sont acceptables.
        return $this->cache->get('dam_anomaly_counter_ouvertes', function (ItemInterface $item): int {
            $item->expiresAfter(60);
            $total = $this->alerts->countPending();
            foreach (DamAnomalyType::cases() as $type) {
                $total += $this->anomalies->countOpen($type);
            }

            return $total;
        });
    }

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
