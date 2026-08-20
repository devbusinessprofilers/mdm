<?php

declare(strict_types=1);

namespace App\Dam\Repository;

use App\Dam\Entity\MediaAsset;
use App\Dam\Entity\MediaRendition;
use App\Dam\Enum\MediaKind;
use App\Dam\Enum\MediaStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/** @extends ServiceEntityRepository<MediaAsset> */
final class MediaAssetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MediaAsset::class);
    }

    /**
     * @param list<string> $ids
     *
     * @return list<MediaAsset>
     */
    public function findByStringIds(array $ids): array
    {
        $ulids = array_map(
            static fn (string $id): Ulid => Ulid::fromString($id),
            array_values(array_filter($ids, Ulid::isValid(...))),
        );
        if ([] === $ulids) {
            return [];
        }

        // Fetch-join renditions so presenters can resolve variant URLs
        // without one lazy collection load per asset.
        return $this->createQueryBuilder('m')
            ->addSelect('r')
            ->leftJoin('m.renditions', 'r')
            ->where('m.id IN (:ids)')
            ->setParameter(
                'ids',
                array_map(
                    static fn (Ulid $id): string => $id->toBinary(),
                    $ulids,
                ),
                ArrayParameterType::BINARY,
            )
            ->getQuery()
            ->getResult();
    }

    public function findOldestActiveImageByChecksum(string $checksum, string $excludedId): ?MediaAsset
    {
        return $this->createQueryBuilder('media')
            ->where('media.checksum = :checksum')
            ->andWhere('media.id <> :excluded')
            ->andWhere('media.kind = :kind')
            ->andWhere('media.status NOT IN (:deleted)')
            ->setParameter('checksum', $checksum)
            // DQL does not run the 'ulid' column type on inferred parameters; bind it explicitly.
            ->setParameter('excluded', Ulid::fromString($excludedId), 'ulid')
            ->setParameter('kind', MediaKind::Image)
            ->setParameter('deleted', [MediaStatus::Deleting, MediaStatus::Deleted])
            ->orderBy('media.createdAt', 'ASC')
            ->addOrderBy('media.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param list<string> $ids
     *
     * @return list<MediaAsset>
     */
    public function findActiveImagesByStringIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        return $this->createQueryBuilder('media')
            ->where('media.id IN (:ids)')
            ->andWhere('media.kind = :kind')
            ->andWhere('media.status NOT IN (:deleted)')
            ->setParameter(
                'ids',
                array_map(static fn (string $id): string => Ulid::fromString($id)->toBinary(), $ids),
                ArrayParameterType::BINARY,
            )
            ->setParameter('kind', MediaKind::Image)
            ->setParameter('deleted', [MediaStatus::Deleting, MediaStatus::Deleted])
            ->getQuery()
            ->getResult();
    }

    /** @return list<string> */
    public function findImageIdsMissingPerceptualHash(?string $afterId, int $limit): array
    {
        $builder = $this->createQueryBuilder('media')
            ->select('media.id')
            ->where('media.kind = :kind')
            ->andWhere('media.perceptualHash IS NULL')
            ->andWhere('media.status NOT IN (:deleted)')
            ->setParameter('kind', MediaKind::Image)
            ->setParameter('deleted', [MediaStatus::Deleting, MediaStatus::Deleted])
            ->orderBy('media.id', 'ASC')
            ->setMaxResults(max(1, min(1000, $limit)));
        if (null !== $afterId) {
            $builder->andWhere('media.id > :after')->setParameter('after', Ulid::fromString($afterId), 'ulid');
        }

        return array_values(array_map(
            static fn (array $row): string => $row['id'] instanceof Ulid ? (string) $row['id'] : (string) Ulid::fromBinary((string) $row['id']),
            $builder->getQuery()->getArrayResult(),
        ));
    }

    /** @return list<array{name: string, nb: int, octets: int}> Volumes par variante générée. */
    public function renditionStats(): array
    {
        /** @var list<array{name: string, nb: string|int, octets: string|int|null}> $rows */
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT name, COUNT(*) AS nb, COALESCE(SUM(size_bytes), 0) AS octets
             FROM dam_media_rendition
             GROUP BY name
             ORDER BY name ASC',
        );

        return array_map(static fn (array $row): array => [
            'name' => (string) $row['name'],
            'nb' => (int) $row['nb'],
            'octets' => (int) $row['octets'],
        ], $rows);
    }

    public function countFailed(): int
    {
        return (int) $this->createQueryBuilder('media')
            ->select('COUNT(media.id)')
            ->where('media.status = :status')
            ->setParameter('status', MediaStatus::Failed)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @param list<string> $ids */
    public function countFailedByStringIds(array $ids): int
    {
        if ([] === $ids) {
            return 0;
        }

        return (int) $this->createQueryBuilder('media')
            ->select('COUNT(media.id)')
            ->where('media.id IN (:ids)')
            ->andWhere('media.status = :status')
            ->setParameter(
                'ids',
                array_map(static fn (string $id): string => Ulid::fromString($id)->toBinary(), $ids),
                ArrayParameterType::BINARY,
            )
            ->setParameter('status', MediaStatus::Failed)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Nombre et poids cumulé des médias actifs — l'indicateur « Stockage ».
     * `octets` ne compte que les originaux ; `octetsTotal` y ajoute les
     * variantes générées (renditions), soit l'espace réellement occupé.
     *
     * @return array{nb: int, octets: int, octetsTotal: int}
     */
    public function storageStats(): array
    {
        $row = $this->createQueryBuilder('media')
            ->select('COUNT(media.id) AS nb', 'COALESCE(SUM(media.sizeBytes), 0) AS octets')
            ->where('media.status NOT IN (:deleted)')
            ->setParameter('deleted', [MediaStatus::Deleting, MediaStatus::Deleted])
            ->getQuery()
            ->getSingleResult();
        // Requête séparée : jointe à la première, la multiplication des lignes
        // par variante fausserait SUM(media.sizeBytes).
        $variantes = (int) $this->getEntityManager()->createQueryBuilder()
            ->select('COALESCE(SUM(rendition.sizeBytes), 0)')
            ->from(MediaRendition::class, 'rendition')
            ->join('rendition.media', 'media')
            ->where('media.status NOT IN (:deleted)')
            ->setParameter('deleted', [MediaStatus::Deleting, MediaStatus::Deleted])
            ->getQuery()
            ->getSingleScalarResult();

        return ['nb' => (int) $row['nb'], 'octets' => (int) $row['octets'], 'octetsTotal' => (int) $row['octets'] + $variantes];
    }

    public function countActiveByKind(MediaKind $kind): int
    {
        return (int) $this->createQueryBuilder('media')
            ->select('COUNT(media.id)')
            ->where('media.kind = :kind')
            ->andWhere('media.status NOT IN (:deleted)')
            ->setParameter('kind', $kind)
            ->setParameter('deleted', [MediaStatus::Deleting, MediaStatus::Deleted])
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<MediaAsset> */
    public function findActiveByKindPage(MediaKind $kind, int $page, int $limit): array
    {
        // Page over ids first: fetch-joining renditions would multiply the rows
        // and break offset pagination.
        $ids = array_values(array_map(
            static fn (array $row): string => $row['id'] instanceof Ulid ? (string) $row['id'] : (string) Ulid::fromBinary((string) $row['id']),
            $this->createQueryBuilder('media')
                ->select('media.id')
                ->where('media.kind = :kind')
                ->andWhere('media.status NOT IN (:deleted)')
                ->setParameter('kind', $kind)
                ->setParameter('deleted', [MediaStatus::Deleting, MediaStatus::Deleted])
                ->orderBy('media.createdAt', 'DESC')
                ->addOrderBy('media.id', 'DESC')
                ->setFirstResult((max(1, $page) - 1) * $limit)
                ->setMaxResults($limit)
                ->getQuery()
                ->getArrayResult(),
        ));
        $byId = [];
        foreach ($this->findByStringIds($ids) as $asset) {
            $byId[$asset->id()] = $asset;
        }

        return array_values(array_filter(array_map(
            static fn (string $id): ?MediaAsset => $byId[$id] ?? null,
            $ids,
        )));
    }

    /** @return list<MediaAsset> */
    public function findFailedPage(int $page, int $limit): array
    {
        return $this->createQueryBuilder('media')
            ->where('media.status = :status')
            ->setParameter('status', MediaStatus::Failed)
            ->orderBy('media.updatedAt', 'DESC')
            ->setFirstResult((max(1, $page) - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
