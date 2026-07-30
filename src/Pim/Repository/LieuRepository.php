<?php

declare(strict_types=1);

namespace App\Pim\Repository;

use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Enum\StatutFiche;
use App\Pim\Enum\TypeFiche;
use App\Pim\ReadModel\FicheCursor;
use App\Pim\ReadModel\LieuListItem;
use App\Pim\ReadModel\LieuListPage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/** @extends ServiceEntityRepository<Lieu> */
final class LieuRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Lieu::class);
    }

    public function findListPage(?FicheCursor $cursor = null, int $limit = 50, ?StatutFiche $status = null): LieuListPage
    {
        $limit = max(1, min(100, $limit));
        $conditions = ['f.type = :type'];
        $parameters = ['type' => TypeFiche::Lieu->value];
        $types = ['type' => ParameterType::STRING];

        if (null !== $status) {
            $conditions[] = 'f.status = :status';
            $parameters['status'] = $status->value;
            $types['status'] = ParameterType::STRING;
        }
        if (null !== $cursor) {
            $conditions[] = '(f.updated_at, f.id) < (:cursor_updated_at, :cursor_id)';
            $parameters['cursor_updated_at'] = $cursor->updatedAt->format('Y-m-d H:i:s');
            $parameters['cursor_id'] = $cursor->id->toBinary();
            $types['cursor_updated_at'] = ParameterType::STRING;
            $types['cursor_id'] = ParameterType::BINARY;
        }

        // MariaDB otherwise tends to start with pim_lieu and filesort the result;
        // keeping pim_fiche first lets the keyset indexes satisfy the ordering.
        $sql = sprintf(<<<'SQL'
            SELECT STRAIGHT_JOIN
                f.id,
                f.code,
                f.label,
                f.status,
                f.completeness,
                f.updated_at,
                loc.ville
            FROM pim_fiche f
            INNER JOIN pim_lieu l ON l.fiche_id = f.id
            LEFT JOIN pim_localisation loc ON loc.id = f.localisation_id
            WHERE %s
            ORDER BY f.updated_at DESC, f.id DESC
            LIMIT %d
            SQL,
            implode("\n  AND ", $conditions),
            $limit + 1,
        );

        /** @var list<array{id: string, code: int|string|null, label: string|null, status: string, completeness: int|string, updated_at: string, ville: string|null}> $rows */
        $rows = $this->getEntityManager()->getConnection()->executeQuery($sql, $parameters, $types)->fetchAllAssociative();
        $hasNext = count($rows) > $limit;
        $rows = array_slice($rows, 0, $limit);
        $items = array_map(self::toListItem(...), $rows);
        $last = [] === $items ? null : $items[array_key_last($items)];

        return new LieuListPage(
            $items,
            $hasNext && null !== $last ? (new FicheCursor($last->updatedAt, Ulid::fromString($last->id)))->encode() : null,
        );
    }

    public function countByStatus(StatutFiche $status): int
    {
        return (int) $this->getEntityManager()->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM pim_fiche f INNER JOIN pim_lieu l ON l.fiche_id = f.id WHERE f.type = :type AND f.status = :status',
            ['type' => TypeFiche::Lieu->value, 'status' => $status->value],
        );
    }

    /**
     * @param list<string> $ids Ordered fiche ULIDs.
     *
     * @return list<LieuListItem>
     */
    public function findListItemsByIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        $binaryIds = array_map(static fn (string $id): string => Ulid::fromString($id)->toBinary(), $ids);
        /** @var list<array{id: string, code: int|string|null, label: string|null, status: string, completeness: int|string, updated_at: string, ville: string|null}> $rows */
        $rows = $this->getEntityManager()->getConnection()->executeQuery(<<<'SQL'
            SELECT
                f.id,
                f.code,
                f.label,
                f.status,
                f.completeness,
                f.updated_at,
                loc.ville
            FROM pim_fiche f
            INNER JOIN pim_lieu l ON l.fiche_id = f.id
            LEFT JOIN pim_localisation loc ON loc.id = f.localisation_id
            WHERE f.type = :type AND f.id IN (:ids)
            SQL,
            ['type' => TypeFiche::Lieu->value, 'ids' => $binaryIds],
            ['type' => ParameterType::STRING, 'ids' => ArrayParameterType::BINARY],
        )->fetchAllAssociative();

        $itemsById = [];
        foreach ($rows as $row) {
            $item = self::toListItem($row);
            $itemsById[$item->id] = $item;
        }

        return array_values(array_filter(array_map(
            static fn (string $id): ?LieuListItem => $itemsById[$id] ?? null,
            $ids,
        )));
    }

    /** @param array{id: string, code: int|string|null, label: string|null, status: string, completeness: int|string, updated_at: string, ville: string|null} $row */
    private static function toListItem(array $row): LieuListItem
    {
        return new LieuListItem(
            id: (string) Ulid::fromBinary($row['id']),
            code: null === $row['code'] ? null : (int) $row['code'],
            label: $row['label'],
            ville: $row['ville'],
            status: StatutFiche::from($row['status']),
            completeness: (int) $row['completeness'],
            updatedAt: new \DateTimeImmutable($row['updated_at']),
        );
    }
}
