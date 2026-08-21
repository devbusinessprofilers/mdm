<?php

declare(strict_types=1);

namespace App\Pim\Repository;

use App\Pim\Entity\TextDuplicateAlert;
use App\Pim\Entity\TextFingerprint;
use App\Pim\Enum\DuplicateReviewStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<TextDuplicateAlert> */
final class TextDuplicateAlertRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TextDuplicateAlert::class);
    }

    public function findForFingerprint(TextFingerprint $fingerprint): ?TextDuplicateAlert
    {
        return $this->findOneBy(['fingerprint' => $fingerprint]);
    }

    public function countPending(): int
    {
        return (int) $this->createQueryBuilder('alert')
            ->select('COUNT(alert.id)')
            ->where('alert.status = :status')
            ->setParameter('status', DuplicateReviewStatus::Pending)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
