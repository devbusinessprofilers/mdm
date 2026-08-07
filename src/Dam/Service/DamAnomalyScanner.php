<?php

declare(strict_types=1);

namespace App\Dam\Service;

use App\Dam\Entity\DamAnomaly;
use App\Dam\Enum\DamAnomalyType;
use App\Dam\Repository\DamAnomalyRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Détections d'incohérences trop coûteuses pour le chemin HTTP, exécutées
 * périodiquement en worker et persistées dans dam_anomaly (les requêtes de
 * détection vivent dans DamAnomalyRepository).
 */
final readonly class DamAnomalyScanner
{
    private const BATCH_SIZE = 5000;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private DamAnomalyRepository $anomalies,
    ) {}

    public function scan(): void
    {
        $this->reconcile(DamAnomalyType::OrphanResource, $this->anomalies->findOrphanResourceIds(self::BATCH_SIZE));
        $this->reconcile(DamAnomalyType::MissingRenditions, $this->anomalies->findMediaIdsMissingRenditions(count(ImageVariantRegistry::names())));
    }

    /** @param list<string> $subjectIds */
    private function reconcile(DamAnomalyType $type, array $subjectIds): void
    {
        $existing = $this->anomalies->allBySubject($type);
        $current = array_fill_keys($subjectIds, true);
        foreach (array_keys($current) as $subjectId) {
            $anomaly = $existing[$subjectId] ?? null;
            if ($anomaly instanceof DamAnomaly) {
                $anomaly->reopen();
            } else {
                $this->entityManager->persist(new DamAnomaly($type, $subjectId));
            }
        }
        foreach ($existing as $subjectId => $anomaly) {
            if (!isset($current[$subjectId])) {
                $anomaly->resolve();
            }
        }
        $this->entityManager->flush();
    }
}
