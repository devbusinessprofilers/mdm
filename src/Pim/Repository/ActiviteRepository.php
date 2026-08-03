<?php

declare(strict_types=1);

namespace App\Pim\Repository;

use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Fiche;
use App\Pim\Enum\StatutFiche;
use App\Pim\Enum\TypeFiche;
use App\Pim\ReadModel\ActiviteListItem;
use App\Pim\ReadModel\ActiviteListPage;
use App\Pim\ReadModel\FicheCursor;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/** @extends ServiceEntityRepository<Activite> */
final class ActiviteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Activite::class);
    }

    public function findOneByFiche(Fiche $fiche): ?Activite
    {
        return $this->findOneBy(['fiche' => $fiche]);
    }

    public function findListPage(
        ?FicheCursor $cursor = null,
        int $limit = 50,
        ?StatutFiche $status = null,
    ): ActiviteListPage {
        $limit = max(1, min(100, $limit));
        $conditions = ['f.type = :type'];
        $params = ['type' => TypeFiche::Activite->value];
        $types = ['type' => ParameterType::STRING];
        if (null !== $status) {
            $conditions[] = 'f.status = :status';
            $params['status'] = $status->value;
            $types['status'] = ParameterType::STRING;
        }
        if (null !== $cursor) {
            $conditions[] = '(f.updated_at, f.id) < (:updated, :id)';
            $params['updated'] = $cursor->updatedAt->format('Y-m-d H:i:s');
            $params['id'] = $cursor->id->toBinary();
            $types['updated'] = ParameterType::STRING;
            $types['id'] = ParameterType::BINARY;
        }
        $sql = sprintf(
            'SELECT STRAIGHT_JOIN f.id, f.code, f.label, f.status, a.completeness_global AS completeness, f.updated_at, CASE WHEN a.mode_intervention = \'fixe\' THEN loc.ville ELSE NULL END ville FROM pim_fiche f INNER JOIN pim_activite a ON a.fiche_id = f.id LEFT JOIN pim_localisation loc ON loc.id = f.localisation_id WHERE %s ORDER BY f.updated_at DESC, f.id DESC LIMIT %d',
            implode(' AND ', $conditions),
            $limit + 1,
        );
        $rows = $this->getEntityManager()
            ->getConnection()
            ->executeQuery($sql, $params, $types)
            ->fetchAllAssociative();
        $hasNext = count($rows) > $limit;
        $rows = array_slice($rows, 0, $limit);
        $items = array_map(self::item(...), $rows);
        $last = [] === $items ? null : $items[array_key_last($items)];

        return new ActiviteListPage(
            $items,
            $hasNext && null !== $last
                ? (new FicheCursor(
                    $last->updatedAt,
                    Ulid::fromString($last->id),
                ))->encode()
                : null,
        );
    }

    public function countByStatus(StatutFiche $status): int
    {
        return (int) $this->getEntityManager()
            ->getConnection()
            ->fetchOne(
                'SELECT COUNT(*) FROM pim_fiche f INNER JOIN pim_activite a ON a.fiche_id=f.id WHERE f.type=? AND f.status=?',
                [TypeFiche::Activite->value, $status->value],
            );
    }

    /**
     * @param list<string> $ids
     *
     * @return list<ActiviteListItem>
     */
    public function findListItemsByIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }
        $binary = array_map(
            static fn (string $id): string => Ulid::fromString($id)->toBinary(),
            $ids,
        );
        $rows = $this->getEntityManager()
            ->getConnection()
            ->executeQuery(
                'SELECT f.id,f.code,f.label,f.status,a.completeness_global AS completeness,f.updated_at,CASE WHEN a.mode_intervention=\'fixe\' THEN loc.ville ELSE NULL END ville FROM pim_fiche f INNER JOIN pim_activite a ON a.fiche_id=f.id LEFT JOIN pim_localisation loc ON loc.id=f.localisation_id WHERE f.type=:type AND f.id IN (:ids)',
                ['type' => TypeFiche::Activite->value, 'ids' => $binary],
                [
                    'type' => ParameterType::STRING,
                    'ids' => ArrayParameterType::BINARY,
                ],
            )
            ->fetchAllAssociative();
        $mapped = [];
        foreach ($rows as $row) {
            $item = self::item($row);
            $mapped[$item->id] = $item;
        }

        return array_values(
            array_filter(
                array_map(
                    static fn (string $id): ?ActiviteListItem => $mapped[$id] ??
                        null,
                    $ids,
                ),
            ),
        );
    }

    /** @param array<string,mixed> $row */
    private static function item(array $row): ActiviteListItem
    {
        return new ActiviteListItem(
            (string) Ulid::fromBinary($row['id']),
            (int) $row['code'],
            $row['label'],
            $row['ville'],
            StatutFiche::from($row['status']),
            (int) $row['completeness'],
            new \DateTimeImmutable($row['updated_at']),
        );
    }
}
