<?php

declare(strict_types=1);

namespace App\Etl\Repository;

use App\Etl\Entity\FicheImportJob;
use App\Etl\Entity\FicheImportJobError;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<FicheImportJobError> */
final class FicheImportJobErrorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FicheImportJobError::class);
    }

    /** @return list<FicheImportJobError> */
    public function findPageForJob(FicheImportJob $job, int $limit = 100, int $offset = 0): array
    {
        return $this->findBy(['job' => $job], ['lineNumber' => 'ASC', 'id' => 'ASC'], max(1, min(500, $limit)), max(0, $offset));
    }

    public function countForJob(FicheImportJob $job): int
    {
        return $this->count(['job' => $job]);
    }
}
