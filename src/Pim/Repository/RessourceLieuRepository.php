<?php

declare(strict_types=1);

namespace App\Pim\Repository;

use App\Dam\Enum\DocumentUsage;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Enum\NatureRessource;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Dam\Enum\RightsValidityStatus;
use App\Pim\Enum\TypeFiche;

/** @extends ServiceEntityRepository<RessourceLieu> */
final class RessourceLieuRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RessourceLieu::class);
    }

    public function findOneByMediaId(string $mediaId): ?RessourceLieu
    {
        return $this->findOneBy(['damAssetId' => $mediaId]);
    }

    public function findDocumentForFiche(Fiche $fiche, string $id, ?DocumentUsage $usage = null): ?RessourceLieu
    {
        $criteria = ['id' => $id, 'fiche' => $fiche, 'nature' => NatureRessource::Document];
        if (null !== $usage) { $criteria['documentUsage'] = $usage; }

        return $this->findOneBy($criteria);
    }

    public function findPhotoForFiche(Fiche $fiche, string $id): ?RessourceLieu
    {
        return $this->findOneBy(['id' => $id, 'fiche' => $fiche, 'nature' => NatureRessource::Photo]);
    }

    /**
     * @param list<string> $mediaIds
     *
     * @return list<RessourceLieu>
     */
    public function findByMediaIds(array $mediaIds): array
    {
        if ([] === $mediaIds) {
            return [];
        }

        return $this->createQueryBuilder('resource')
            ->addSelect('fiche')
            ->join('resource.fiche', 'fiche')
            ->where('resource.damAssetId IN (:mediaIds)')
            ->setParameter('mediaIds', $mediaIds)
            ->getQuery()
            ->getResult();
    }

    public function countByRightsStatus(RightsValidityStatus $status, ?TypeFiche $type = null, ?\DateTimeImmutable $today = null): int
    {
        $builder = $this->rightsQuery($status, $type, $today)->select('COUNT(resource.id)');

        return (int) $builder->getQuery()->getSingleScalarResult();
    }

    public function countByRightsStatusForFiche(Fiche $fiche, RightsValidityStatus $status, ?\DateTimeImmutable $today = null): int
    {
        return (int) $this->rightsQuery($status, null, $today)
            ->select('COUNT(resource.id)')
            ->andWhere('resource.fiche = :fiche')
            ->setParameter('fiche', $fiche->id(), 'ulid')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<RessourceLieu> */
    public function findRightsPage(RightsValidityStatus $status, ?TypeFiche $type, int $page, int $limit, ?\DateTimeImmutable $today = null): array
    {
        return $this->rightsQuery($status, $type, $today)
            ->addSelect('fiche')
            ->orderBy('resource.rightsExpiresAt', 'ASC')
            ->addOrderBy('resource.id', 'ASC')
            ->setFirstResult((max(1, $page) - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    private function rightsQuery(RightsValidityStatus $status, ?TypeFiche $type, ?\DateTimeImmutable $today): \Doctrine\ORM\QueryBuilder
    {
        $today = ($today ?? new \DateTimeImmutable('today'))->setTime(0, 0);
        $builder = $this->createQueryBuilder('resource')
            ->join('resource.fiche', 'fiche');
        if (RightsValidityStatus::NotGranted === $status) {
            $builder->where('resource.rightsGranted = false');
        } elseif (RightsValidityStatus::Expired === $status) {
            $builder->where('resource.rightsGranted = true')
                ->andWhere('resource.rightsExpiresAt < :today')->setParameter('today', $today);
        } elseif (RightsValidityStatus::Expiring === $status) {
            $builder->where('resource.rightsGranted = true')
                ->andWhere('resource.rightsExpiresAt >= :today')
                ->andWhere('resource.rightsExpiresAt <= :limitDate')
                ->setParameter('today', $today)
                ->setParameter('limitDate', $today->modify('+30 days'));
        } else {
            throw new \InvalidArgumentException('Statut de droits non pris en charge par le dashboard.');
        }
        if (null !== $type) {
            $builder->andWhere('fiche.type = :type')->setParameter('type', $type);
        }

        return $builder;
    }
}
