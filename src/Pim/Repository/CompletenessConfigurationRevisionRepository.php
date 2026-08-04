<?php

declare(strict_types=1);

namespace App\Pim\Repository;

use App\Pim\Entity\CompletenessConfigurationRevision;
use App\Pim\Enum\TypeFiche;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CompletenessConfigurationRevision> */
final class CompletenessConfigurationRevisionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CompletenessConfigurationRevision::class);
    }

    public function findForType(TypeFiche $type): ?CompletenessConfigurationRevision
    {
        return $this->find($type);
    }

    public function findForUpdate(TypeFiche $type): ?CompletenessConfigurationRevision
    {
        return $this->find($type, LockMode::PESSIMISTIC_WRITE);
    }
}
