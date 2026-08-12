<?php

declare(strict_types=1);

namespace App\Etl\Repository;

use App\Etl\Entity\LegacyFicheMapping;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<LegacyFicheMapping> */
final class LegacyFicheMappingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LegacyFicheMapping::class);
    }

    /** @return list<int> */
    public function allSyspadIds(): array
    {
        $rows = $this->createQueryBuilder('m')->select('m.syspadId')->getQuery()->getSingleColumnResult();

        return array_values(array_map(intval(...), $rows));
    }

    /** @return list<LegacyFicheMapping> mappings dont les photos n'ont pas encore été déclinées */
    public function findPhotosNotSeeded(int $limit = 500): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.photosSeededAt IS NULL')
            ->andWhere('m.photosJson IS NOT NULL')
            ->orderBy('m.syspadId', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
