<?php

declare(strict_types=1);

namespace App\Pim\Repository;

use App\Dam\Enum\DocumentUsage;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Enum\NatureRessource;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\Persistence\ManagerRegistry;
use App\Dam\Enum\RightsValidityStatus;
use App\Pim\Enum\TypeFiche;
use App\Shared\Service\ParametreProviderInterface;
use Symfony\Component\Uid\Ulid;

/** @extends ServiceEntityRepository<RessourceLieu> */
final class RessourceLieuRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly ParametreProviderInterface $parametres,
    ) {
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
    /**
     * @param list<string> $ids
     *
     * @return list<RessourceLieu>
     */
    public function findByIds(array $ids): array
    {
        $ulids = array_values(array_filter($ids, Ulid::isValid(...)));
        if ([] === $ulids) {
            return [];
        }

        return $this->createQueryBuilder('resource')
            ->addSelect('fiche')
            ->join('resource.fiche', 'fiche')
            ->where('resource.id IN (:ids)')
            // DQL n'applique pas le type 'ulid' aux paramètres inférés : lier
            // les valeurs binaires explicitement.
            ->setParameter(
                'ids',
                array_map(static fn (string $id): string => Ulid::fromString($id)->toBinary(), $ulids),
                ArrayParameterType::BINARY,
            )
            ->getQuery()
            ->getResult();
    }

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

    public function countPublishedRightsIssues(?TypeFiche $type = null, ?\DateTimeImmutable $today = null): int
    {
        return (int) $this->publishedRightsIssuesQuery($type, $today)
            ->select('COUNT(resource.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<RessourceLieu> */
    public function findPublishedRightsIssuesPage(?TypeFiche $type, int $page, int $limit, ?\DateTimeImmutable $today = null): array
    {
        return $this->publishedRightsIssuesQuery($type, $today)
            ->addSelect('fiche')
            ->orderBy('resource.rightsExpiresAt', 'ASC')
            ->addOrderBy('resource.id', 'ASC')
            ->setFirstResult((max(1, $page) - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** Photos de fiches publiées dont les droits sont absents ou expirés. */
    private function publishedRightsIssuesQuery(?TypeFiche $type, ?\DateTimeImmutable $today): \Doctrine\ORM\QueryBuilder
    {
        $today = ($today ?? new \DateTimeImmutable('today'))->setTime(0, 0);
        $builder = $this->createQueryBuilder('resource')
            ->join('resource.fiche', 'fiche')
            ->where('fiche.status = :published')
            ->andWhere('resource.nature = :photo')
            ->andWhere('(resource.rightsGranted = false OR resource.rightsExpiresAt < :today)')
            ->setParameter('published', \App\Pim\Enum\StatutFiche::Publiee)
            ->setParameter('photo', NatureRessource::Photo)
            ->setParameter('today', $today);
        if (null !== $type) {
            $builder->andWhere('fiche.type = :type')->setParameter('type', $type);
        }

        return $builder;
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
                ->setParameter('limitDate', $today->modify(sprintf(
                    '+%d days',
                    $this->parametres->int('dam.delai_alerte_droits_jours'),
                )));
        } else {
            throw new \InvalidArgumentException('Statut de droits non pris en charge par le dashboard.');
        }
        if (null !== $type) {
            $builder->andWhere('fiche.type = :type')->setParameter('type', $type);
        }

        return $builder;
    }
}
