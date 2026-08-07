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

    public function latest(string $kind = DashboardSnapshot::KIND_STATS): ?DashboardSnapshot
    {
        return $this->createQueryBuilder('s')
            ->where('s.kind = :kind')
            ->setParameter('kind', $kind)
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
            ->andWhere('s.kind = :kind')
            ->setParameter('since', $since)
            ->setParameter('kind', DashboardSnapshot::KIND_STATS)
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
        // Partitionné par kind : un snapshot stats et un field_fill peuvent
        // coexister sur la même journée sans s'entre-supprimer.
        $keptByDay = [];
        foreach ($snapshots as $snapshot) {
            $keptByDay[$snapshot->kind().'|'.$snapshot->computedAt()->format('Y-m-d')] = $snapshot;
        }
        $removed = 0;
        foreach ($snapshots as $snapshot) {
            if ($keptByDay[$snapshot->kind().'|'.$snapshot->computedAt()->format('Y-m-d')] !== $snapshot) {
                $this->getEntityManager()->remove($snapshot);
                ++$removed;
            }
        }

        return $removed;
    }
}
