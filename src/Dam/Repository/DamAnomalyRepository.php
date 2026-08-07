<?php

declare(strict_types=1);

namespace App\Dam\Repository;

use App\Dam\Entity\DamAnomaly;
use App\Dam\Enum\DamAnomalyType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/** @extends ServiceEntityRepository<DamAnomaly> */
final class DamAnomalyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DamAnomaly::class);
    }

    public function countOpen(DamAnomalyType $type): int
    {
        return (int) $this->createQueryBuilder('anomaly')
            ->select('COUNT(anomaly.id)')
            ->where('anomaly.type = :type')
            ->andWhere('anomaly.resolvedAt IS NULL')
            ->setParameter('type', $type)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<DamAnomaly> */
    public function findOpenPage(DamAnomalyType $type, int $page, int $limit): array
    {
        return $this->createQueryBuilder('anomaly')
            ->where('anomaly.type = :type')
            ->andWhere('anomaly.resolvedAt IS NULL')
            ->setParameter('type', $type)
            ->orderBy('anomaly.detectedAt', 'DESC')
            ->addOrderBy('anomaly.id', 'DESC')
            ->setFirstResult((max(1, $page) - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Ressources dont le média DAM est introuvable ou supprimé. La comparaison
     * ne peut pas se faire en SQL (dam_asset_id est un ULID base32,
     * dam_media_asset.id un BINARY(16)) : conversion en PHP par lots.
     *
     * @return list<string>
     */
    public function findOrphanResourceIds(int $batchSize): array
    {
        $connection = $this->getEntityManager()->getConnection();
        $orphans = [];
        $cursor = '';
        while (true) {
            /** @var list<array{id: string, dam_asset_id: string}> $rows */
            $rows = $connection->fetchAllAssociative(
                'SELECT id, dam_asset_id FROM pim_ressource_lieu WHERE id > :cursor ORDER BY id LIMIT '.max(1, $batchSize),
                ['cursor' => $cursor],
                ['cursor' => ParameterType::BINARY],
            );
            if ([] === $rows) {
                break;
            }
            $cursor = $rows[array_key_last($rows)]['id'];

            $assetBinaryByResource = [];
            foreach ($rows as $row) {
                $resourceId = (string) Ulid::fromBinary($row['id']);
                if (!Ulid::isValid($row['dam_asset_id'])) {
                    $orphans[] = $resourceId;
                    continue;
                }
                $assetBinaryByResource[$resourceId] = Ulid::fromString($row['dam_asset_id'])->toBinary();
            }
            if ([] === $assetBinaryByResource) {
                continue;
            }
            // Les médias en échec ne sont pas orphelins : ils ont leur filtre « failed ».
            $known = $connection->fetchFirstColumn(
                "SELECT id FROM dam_media_asset WHERE id IN (:ids) AND status NOT IN ('deleting', 'deleted')",
                ['ids' => array_values(array_unique($assetBinaryByResource))],
                ['ids' => ArrayParameterType::BINARY],
            );
            $knownSet = array_fill_keys(array_map(strval(...), $known), true);
            foreach ($assetBinaryByResource as $resourceId => $assetBinary) {
                if (!isset($knownSet[$assetBinary])) {
                    $orphans[] = $resourceId;
                }
            }
        }

        return $orphans;
    }

    /**
     * Médias image « processed » auxquels il manque au moins une variante.
     *
     * @return list<string>
     */
    public function findMediaIdsMissingRenditions(int $expectedRenditionCount): array
    {
        $ids = $this->getEntityManager()->getConnection()->fetchFirstColumn(
            "SELECT m.id
             FROM dam_media_asset m
             LEFT JOIN dam_media_rendition r ON r.media_id = m.id
             WHERE m.status = 'processed' AND m.kind = 'image'
             GROUP BY m.id
             HAVING COUNT(DISTINCT r.name) < :expected",
            ['expected' => $expectedRenditionCount],
        );

        return array_map(static fn (mixed $id): string => (string) Ulid::fromBinary((string) $id), $ids);
    }

    /** @return array<string, DamAnomaly> anomalies du type, indexées par sujet */
    public function allBySubject(DamAnomalyType $type): array
    {
        $bySubject = [];
        foreach ($this->findBy(['type' => $type]) as $anomaly) {
            $bySubject[$anomaly->subjectId()] = $anomaly;
        }

        return $bySubject;
    }
}
