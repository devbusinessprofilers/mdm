<?php

declare(strict_types=1);

namespace App\Dam\Repository;

use App\Dam\Entity\MediaAsset;
use App\Dam\Entity\MediaDuplicateAlert;
use App\Dam\Enum\DuplicateReviewStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<MediaDuplicateAlert> */
final class MediaDuplicateAlertRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MediaDuplicateAlert::class);
    }

    public function findForMedia(MediaAsset $media): ?MediaDuplicateAlert
    {
        return $this->findOneBy(['media' => $media]);
    }

    public function countPending(?string $ficheType = null): int
    {
        $builder = $this->createQueryBuilder('alert')
            ->select('COUNT(alert.id)')
            ->where('alert.status = :status')
            ->setParameter('status', DuplicateReviewStatus::Pending);
        if (null !== $ficheType) {
            $builder->andWhere('alert.ficheType = :ficheType')->setParameter('ficheType', $ficheType);
        }

        return (int) $builder->getQuery()->getSingleScalarResult();
    }

    public function countPendingForFiche(string $ficheId): int
    {
        return (int) $this->createQueryBuilder('alert')
            ->select('COUNT(alert.id)')
            ->where('alert.status = :status')
            ->andWhere('alert.ficheId = :ficheId')
            ->setParameter('status', DuplicateReviewStatus::Pending)
            ->setParameter('ficheId', $ficheId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<MediaDuplicateAlert> */
    public function findPendingPage(?string $ficheType, int $page, int $limit): array
    {
        $builder = $this->createQueryBuilder('alert')
            ->addSelect('media', 'reference')
            ->join('alert.media', 'media')
            ->join('alert.duplicateOf', 'reference')
            ->where('alert.status = :status')
            ->setParameter('status', DuplicateReviewStatus::Pending)
            ->orderBy('alert.createdAt', 'DESC')
            ->setFirstResult((max(1, $page) - 1) * $limit)
            ->setMaxResults($limit);
        if (null !== $ficheType) {
            $builder->andWhere('alert.ficheType = :ficheType')->setParameter('ficheType', $ficheType);
        }

        return $builder->getQuery()->getResult();
    }
}
