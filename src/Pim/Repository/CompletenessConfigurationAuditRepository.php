<?php

declare(strict_types=1);

namespace App\Pim\Repository;

use App\Pim\Entity\CompletenessConfigurationAudit;
use App\Pim\Enum\TypeFiche;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CompletenessConfigurationAudit> */
final class CompletenessConfigurationAuditRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CompletenessConfigurationAudit::class);
    }

    /** @return list<CompletenessConfigurationAudit> */
    public function recentForField(TypeFiche $type, string $fieldCode, int $limit = 20): array
    {
        return $this->createQueryBuilder('audit')
            ->andWhere('audit.ficheType = :type')->setParameter('type', $type)
            ->andWhere('audit.fieldCode = :code')->setParameter('code', strtoupper($fieldCode))
            ->orderBy('audit.changedAt', 'DESC')
            ->setMaxResults(max(1, min(100, $limit)))
            ->getQuery()->getResult();
    }
}
