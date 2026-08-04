<?php

declare(strict_types=1);

namespace App\Pim\Repository;

use App\Pim\Entity\Fiche;
use App\Pim\Enum\StatutFiche;
use App\Pim\Enum\TypeFiche;
use App\Pim\ReadModel\GlobalSearchItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/** @extends ServiceEntityRepository<Fiche> */
final class FicheRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Fiche::class);
    }

    public function findOneByTypeAndCode(TypeFiche $type, int $code): ?Fiche
    {
        return $this->findOneBy(['type' => $type, 'code' => $code]);
    }

    public function countByType(TypeFiche $type): int
    {
        return (int) $this->getEntityManager()->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM pim_fiche WHERE type = :type',
            ['type' => $type->value],
            ['type' => ParameterType::STRING],
        );
    }

    public function countByTypeAndStatus(TypeFiche $type, StatutFiche $status): int
    {
        return (int) $this->getEntityManager()->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM pim_fiche WHERE type = :type AND status = :status',
            ['type' => $type->value, 'status' => $status->value],
            ['type' => ParameterType::STRING, 'status' => ParameterType::STRING],
        );
    }

    /** @return list<Fiche> */
    public function findBatchAfter(TypeFiche $type, ?string $afterId, int $limit): array
    {
        $limit = max(1, min(1000, $limit));
        $sql = 'SELECT id FROM pim_fiche WHERE type = :type';
        $params = ['type' => $type->value];
        $types = ['type' => ParameterType::STRING];
        if (null !== $afterId) {
            $sql .= ' AND id > :after_id';
            $params['after_id'] = Ulid::fromString($afterId)->toBinary();
            $types['after_id'] = ParameterType::BINARY;
        }
        $sql .= ' ORDER BY id ASC LIMIT '.$limit;
        $rows = $this->getEntityManager()->getConnection()->executeQuery($sql, $params, $types)->fetchFirstColumn();
        if ([] === $rows) {
            return [];
        }
        $ids = array_map(static fn (string $binaryId): Ulid => Ulid::fromBinary($binaryId), $rows);
        /** @var list<Fiche> $fiches */
        $fiches = $this->findBy(['id' => $ids], ['id' => 'ASC']);

        return $fiches;
    }

    /** @return list<Fiche> */
    public function findPublishedAfter(?string $afterId, int $limit): array
    {
        $builder = $this->createQueryBuilder('fiche')
            ->andWhere('fiche.status = :status')
            ->setParameter('status', StatutFiche::Publiee)
            ->orderBy('fiche.id', 'ASC')
            ->setMaxResults(max(1, min(1000, $limit)));
        if (null !== $afterId && '' !== $afterId) {
            $builder->andWhere('fiche.id > :after')->setParameter('after', Ulid::fromString($afterId), 'ulid');
        }

        /** @var list<Fiche> $fiches */
        $fiches = $builder->getQuery()->getResult();

        return $fiches;
    }

    /**
     * @param list<string> $ids Ordered fiche ULIDs, in search relevance order.
     *
     * @return list<GlobalSearchItem>
     */
    public function findGlobalSearchItemsByIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        $binaryIds = array_map(static fn (string $id): string => Ulid::fromString($id)->toBinary(), $ids);
        /** @var list<array{id: string, type: string, code: int|string, label: string|null, ville: string|null, status: string, completeness: int|string, updated_at: string}> $rows */
        $rows = $this->getEntityManager()->getConnection()->executeQuery(<<<'SQL'
            SELECT
                f.id,
                f.type,
                f.code,
                f.label,
                CASE f.type
                    WHEN 'activite' THEN CASE WHEN a.mode_intervention = 'fixe' THEN loc.ville ELSE NULL END
                    WHEN 'service_evenementiel' THEN CASE WHEN s.mode_intervention = 'fixe' THEN loc.ville ELSE NULL END
                    ELSE loc.ville
                END AS ville,
                f.status,
                CASE f.type
                    WHEN 'lieu' THEN l.completeness_global
                    WHEN 'activite' THEN a.completeness_global
                    WHEN 'restaurant' THEN r.completeness_global
                    WHEN 'service_evenementiel' THEN s.completeness_global
                    ELSE 0
                END AS completeness,
                f.updated_at
            FROM pim_fiche f
            LEFT JOIN pim_lieu l ON l.fiche_id = f.id AND f.type = 'lieu'
            LEFT JOIN pim_activite a ON a.fiche_id = f.id AND f.type = 'activite'
            LEFT JOIN pim_restaurant r ON r.fiche_id = f.id AND f.type = 'restaurant'
            LEFT JOIN pim_service_evenementiel s ON s.fiche_id = f.id AND f.type = 'service_evenementiel'
            LEFT JOIN pim_localisation loc ON loc.id = f.localisation_id
            WHERE f.id IN (:ids)
            SQL,
            ['ids' => $binaryIds],
            ['ids' => ArrayParameterType::BINARY],
        )->fetchAllAssociative();

        $itemsById = [];
        foreach ($rows as $row) {
            $item = new GlobalSearchItem(
                id: (string) Ulid::fromBinary($row['id']),
                type: TypeFiche::from($row['type']),
                code: (int) $row['code'],
                label: $row['label'],
                ville: $row['ville'],
                status: StatutFiche::from($row['status']),
                completeness: (int) $row['completeness'],
                updatedAt: new \DateTimeImmutable($row['updated_at']),
            );
            $itemsById[$item->id] = $item;
        }

        return array_values(array_filter(array_map(
            static fn (string $id): ?GlobalSearchItem => $itemsById[$id] ?? null,
            $ids,
        )));
    }
}
