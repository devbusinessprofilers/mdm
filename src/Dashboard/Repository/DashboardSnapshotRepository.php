<?php

declare(strict_types=1);

namespace App\Dashboard\Repository;

use App\Dashboard\Entity\DashboardSnapshot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<DashboardSnapshot> */
final class DashboardSnapshotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DashboardSnapshot::class);
    }

    public function latest(): ?DashboardSnapshot
    {
        return $this->createQueryBuilder('s')
            ->orderBy('s.computedAt', 'DESC')
            ->addOrderBy('s.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Dernier snapshot de chaque journée sur les N derniers jours, du plus ancien au plus récent.
     *
     * @return list<DashboardSnapshot>
     */
    public function dailyHistory(int $days): array
    {
        $since = new \DateTimeImmutable(sprintf('-%d days midnight', max(1, $days)));
        /** @var list<DashboardSnapshot> $snapshots */
        $snapshots = $this->createQueryBuilder('s')
            ->where('s.computedAt >= :since')
            ->setParameter('since', $since)
            ->orderBy('s.computedAt', 'ASC')
            ->addOrderBy('s.id', 'ASC')
            ->getQuery()
            ->getResult();
        $byDay = [];
        foreach ($snapshots as $snapshot) {
            $byDay[$snapshot->computedAt()->format('Y-m-d')] = $snapshot;
        }

        return array_values($byDay);
    }

    /**
     * Compacte l'historique : au-delà de la limite, seul le dernier snapshot
     * de chaque journée est conservé (granularité quotidienne, sans purge).
     */
    public function compactOlderThan(\DateTimeImmutable $limit): int
    {
        /** @var list<DashboardSnapshot> $snapshots */
        $snapshots = $this->createQueryBuilder('s')
            ->where('s.computedAt < :limit')
            ->setParameter('limit', $limit)
            ->orderBy('s.computedAt', 'ASC')
            ->addOrderBy('s.id', 'ASC')
            ->getQuery()
            ->getResult();
        $keptByDay = [];
        foreach ($snapshots as $snapshot) {
            $keptByDay[$snapshot->computedAt()->format('Y-m-d')] = $snapshot;
        }
        $removed = 0;
        foreach ($snapshots as $snapshot) {
            if ($keptByDay[$snapshot->computedAt()->format('Y-m-d')] !== $snapshot) {
                $this->getEntityManager()->remove($snapshot);
                ++$removed;
            }
        }

        return $removed;
    }
}
