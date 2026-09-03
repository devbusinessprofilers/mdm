<?php

declare(strict_types=1);

namespace App\Pim\Repository;

use App\Dam\Enum\DocumentUsage;
use App\Dam\Enum\RightsValidityStatus;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Enum\NatureRessource;
use App\Pim\Enum\TypeFiche;
use App\Shared\Service\ParametreProviderInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\Persistence\ManagerRegistry;
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
        // Le champ Doctrine est `usage` (usage_code, chaîne) : documentUsage()
        // n'est qu'un accesseur dérivé, le filtrer levait UnrecognizedField.
        // Les alias legacy couvrent les usages renommés par documentUsage().
        $criteria = ['id' => $id, 'fiche' => $fiche, 'nature' => NatureRessource::Document];
        if (null !== $usage) {
            $criteria['usage'] = match ($usage) {
                DocumentUsage::RseEvidence => ['rse', $usage->value],
                DocumentUsage::GeneralPlan => ['plan_general', $usage->value],
                default => $usage->value,
            };
        }

        return $this->findOneBy($criteria);
    }

    public function findPhotoForFiche(Fiche $fiche, string $id): ?RessourceLieu
    {
        return $this->findOneBy(['id' => $id, 'fiche' => $fiche, 'nature' => NatureRessource::Photo]);
    }

    /** Photos rattachées à une fiche et adossées à un média DAM — l'assiette de la reconnaissance IA. */
    /**
     * Photos rattachées à un média, avec la gamme de leur fiche : sert au
     * reclassement des objets du bucket par segment de gamme.
     *
     * @return list<array{asset: string, fiche_id: string, type: string}>
     */
    public function photosAvecGamme(): array
    {
        /** @var list<array{asset: string, fiche_id: string, type: string}> $lignes */
        $lignes = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            "SELECT r.dam_asset_id AS asset, r.fiche_id, f.type FROM pim_ressource_lieu r INNER JOIN pim_fiche f ON f.id = r.fiche_id WHERE r.nature = 'photo' AND r.dam_asset_id <> ''",
        );

        return $lignes;
    }

    public function countPhotosAvecMedia(): int
    {
        return (int) $this->photosAvecMediaQuery()
            ->select('COUNT(resource.id)')
            ->getQuery()->getSingleScalarResult();
    }

    /** Photos dont le champ mots-clés est vide : candidates à la reconnaissance en masse. */
    public function countPhotosSansMotsCles(): int
    {
        return (int) $this->photosSansMotsClesQuery()
            ->select('COUNT(resource.id)')
            ->getQuery()->getSingleScalarResult();
    }

    private function photosAvecMediaQuery(): \Doctrine\ORM\QueryBuilder
    {
        return $this->createQueryBuilder('resource')
            ->join('resource.fiche', 'fiche')
            ->andWhere('resource.nature = :photo')
            ->andWhere("resource.damAssetId <> ''")
            ->setParameter('photo', NatureRessource::Photo);
    }

    private function photosSansMotsClesQuery(): \Doctrine\ORM\QueryBuilder
    {
        return $this->photosAvecMediaQuery()
            ->andWhere("resource.keywords IS NULL OR TRIM(resource.keywords) = ''");
    }

    /**
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

    /**
     * Mémorisation par requête : les écrans médias comptent les mêmes régimes
     * dans les indicateurs, la répartition et les files — chaque combinaison ne
     * vaut qu'un COUNT (les tables ne bougent pas pendant un GET).
     *
     * @var array<string, int>
     */
    private array $rightsCounts = [];

    public function countByRightsStatus(RightsValidityStatus $status, ?TypeFiche $type = null, ?\DateTimeImmutable $today = null): int
    {
        $cle = $status->value.'|'.($type->value ?? '').'|'.($today?->format(\DateTimeInterface::ATOM) ?? '');

        return $this->rightsCounts[$cle] ??= (int) $this->rightsQuery($status, $type, $today)
            ->select('COUNT(resource.id)')
            ->getQuery()
            ->getSingleScalarResult();
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
        } elseif (RightsValidityStatus::Unlimited === $status) {
            $builder->where('resource.rightsGranted = true')
                ->andWhere('resource.rightsExpiresAt IS NULL');
        } elseif (RightsValidityStatus::Valid === $status) {
            // Au-delà de la fenêtre d'alerte : valides sans être « à échéance ».
            $builder->where('resource.rightsGranted = true')
                ->andWhere('resource.rightsExpiresAt > :limitDate')
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
