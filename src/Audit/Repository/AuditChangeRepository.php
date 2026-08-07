<?php

declare(strict_types=1);

namespace App\Audit\Repository;

use App\Audit\Entity\AuditChange;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AuditChange> */
final class AuditChangeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditChange::class);
    }
}
