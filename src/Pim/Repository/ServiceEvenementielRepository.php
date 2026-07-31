<?php

declare(strict_types=1);

namespace App\Pim\Repository;

use App\Pim\Entity\Fiche;
use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Pim\Enum\StatutFiche;
use App\Pim\Enum\TypeFiche;
use App\Pim\ReadModel\FicheCursor;
use App\Pim\ReadModel\ServiceEvenementielListItem;
use App\Pim\ReadModel\ServiceEvenementielListPage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/** @extends ServiceEntityRepository<ServiceEvenementiel> */
final class ServiceEvenementielRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ServiceEvenementiel::class);
    }

    public function findOneByFiche(Fiche $fiche): ?ServiceEvenementiel
    {
        return $this->findOneBy(["fiche" => $fiche]);
    }

    public function findListPage(
        ?FicheCursor $cursor = null,
        int $limit = 50,
        ?StatutFiche $status = null,
    ): ServiceEvenementielListPage {
        $limit = max(1, min(100, $limit));
        $conditions = ["f.type = :type"];
        $parameters = ["type" => TypeFiche::ServiceEvenementiel->value];
        $types = ["type" => ParameterType::STRING];
        if (null !== $status) {
            $conditions[] = "f.status = :status";
            $parameters["status"] = $status->value;
            $types["status"] = ParameterType::STRING;
        }
        if (null !== $cursor) {
            $conditions[] = "(f.updated_at, f.id) < (:updated, :id)";
            $parameters["updated"] = $cursor->updatedAt->format("Y-m-d H:i:s");
            $parameters["id"] = $cursor->id->toBinary();
            $types["updated"] = ParameterType::STRING;
            $types["id"] = ParameterType::BINARY;
        }
        $sql = sprintf(
            "SELECT f.id, f.code, f.label, f.status, f.completeness, f.updated_at, CASE WHEN s.mode_intervention = 'fixe' THEN loc.ville ELSE NULL END ville FROM pim_fiche f INNER JOIN pim_service_evenementiel s ON s.fiche_id=f.id LEFT JOIN pim_localisation loc ON loc.id=f.localisation_id WHERE %s ORDER BY f.updated_at DESC, f.id DESC LIMIT %d",
            implode(" AND ", $conditions),
            $limit + 1,
        );
        $rows = $this->getEntityManager()
            ->getConnection()
            ->executeQuery($sql, $parameters, $types)
            ->fetchAllAssociative();
        $hasNext = count($rows) > $limit;
        $items = array_map(self::item(...), array_slice($rows, 0, $limit));
        $last = [] === $items ? null : $items[array_key_last($items)];

        return new ServiceEvenementielListPage(
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
                "SELECT COUNT(*) FROM pim_fiche f INNER JOIN pim_service_evenementiel s ON s.fiche_id=f.id WHERE f.type=? AND f.status=?",
                [TypeFiche::ServiceEvenementiel->value, $status->value],
            );
    }

    /** @param list<string> $ids
     *  @return list<ServiceEvenementielListItem>
     */
    public function findListItemsByIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }
        $rows = $this->getEntityManager()
            ->getConnection()
            ->executeQuery(
                "SELECT f.id,f.code,f.label,f.status,f.completeness,f.updated_at,CASE WHEN s.mode_intervention='fixe' THEN loc.ville ELSE NULL END ville FROM pim_fiche f INNER JOIN pim_service_evenementiel s ON s.fiche_id=f.id LEFT JOIN pim_localisation loc ON loc.id=f.localisation_id WHERE f.type=:type AND f.id IN (:ids)",
                [
                    "type" => TypeFiche::ServiceEvenementiel->value,
                    "ids" => array_map(
                        static fn(string $id): string => Ulid::fromString(
                            $id,
                        )->toBinary(),
                        $ids,
                    ),
                ],
                [
                    "type" => ParameterType::STRING,
                    "ids" => ArrayParameterType::BINARY,
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
                    static fn(
                        string $id,
                    ): ?ServiceEvenementielListItem => $mapped[$id] ?? null,
                    $ids,
                ),
            ),
        );
    }

    /** @param array<string, mixed> $row */
    private static function item(array $row): ServiceEvenementielListItem
    {
        return new ServiceEvenementielListItem(
            (string) Ulid::fromBinary($row["id"]),
            null === $row["code"] ? null : (int) $row["code"],
            $row["label"],
            $row["ville"],
            StatutFiche::from($row["status"]),
            (int) $row["completeness"],
            new \DateTimeImmutable($row["updated_at"]),
        );
    }
}
