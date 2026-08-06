<?php

declare(strict_types=1);

namespace App\Etl\Repository;

use App\Etl\Entity\LegacyPhotoImport;
use App\Etl\Enum\LegacyPhotoStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<LegacyPhotoImport> */
final class LegacyPhotoImportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LegacyPhotoImport::class);
    }

    /**
     * Photos à traiter, groupées par syspad_id croissant pour limiter les
     * rechargements de Lieu. Le shard filtre par modulo sur syspad_id.
     *
     * @return list<LegacyPhotoImport>
     */
    public function findPending(int $limit, ?int $syspadId = null, ?int $shardIndex = null, ?int $shardCount = null): array
    {
        $builder = $this->createQueryBuilder('p')
            ->where('p.status = :status')
            ->setParameter('status', LegacyPhotoStatus::Pending)
            ->orderBy('p.syspadId', 'ASC')
            ->addOrderBy('p.position', 'ASC')
            ->setMaxResults($limit);
        if (null !== $syspadId) {
            $builder->andWhere('p.syspadId = :syspad')->setParameter('syspad', $syspadId);
        }
        if (null !== $shardIndex && null !== $shardCount) {
            $builder->andWhere('MOD(p.syspadId, :shardCount) = :shardIndex')
                ->setParameter('shardCount', $shardCount)
                ->setParameter('shardIndex', $shardIndex);
        }

        return $builder->getQuery()->getResult();
    }

    public function resetErrorsToPending(): int
    {
        return $this->createQueryBuilder('p')
            ->update()
            ->set('p.status', ':pending')
            ->where('p.status = :error')
            ->setParameter('pending', LegacyPhotoStatus::Pending)
            ->setParameter('error', LegacyPhotoStatus::Error)
            ->getQuery()
            ->execute();
    }

    /** @return array<string, int> répartition par statut */
    public function countByStatus(): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('p.status AS status, COUNT(p.id) AS total')
            ->groupBy('p.status')
            ->getQuery()
            ->getArrayResult();
        $counts = [];
        foreach ($rows as $row) {
            $status = $row['status'] instanceof LegacyPhotoStatus ? $row['status']->value : (string) $row['status'];
            $counts[$status] = (int) $row['total'];
        }
        ksort($counts);

        return $counts;
    }
}
