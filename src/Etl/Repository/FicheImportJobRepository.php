<?php

declare(strict_types=1);

namespace App\Etl\Repository;

use App\Etl\Entity\FicheImportJob;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<FicheImportJob> */
final class FicheImportJobRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FicheImportJob::class);
    }

    /** @return list<FicheImportJob> */
    public function findRecent(int $limit = 50): array
    {
        return $this->findBy([], ['createdAt' => 'DESC'], max(1, min(200, $limit)));
    }
}
