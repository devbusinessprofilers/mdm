<?php

declare(strict_types=1);

namespace App\Pim\Repository;

use App\Pim\Entity\Fiche;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Enum\StatutFiche;
use App\Pim\Enum\TypeFiche;
use App\Pim\ReadModel\FicheCursor;
use App\Pim\ReadModel\RestaurantListItem;
use App\Pim\ReadModel\RestaurantListPage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/** @extends ServiceEntityRepository<Restaurant> */
final class RestaurantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Restaurant::class);
    }

    /**
     * Sélection pour l'autocomplete (liaison Lieu → Restaurant) : fiches non
     * archivées. Alias « f » et non « fiche » : EntitySearchUtil joint le sien.
     */
    public function createAutocompleteQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('r')
            ->addSelect('f')
            ->innerJoin('r.fiche', 'f')
            ->andWhere('f.status != :archivee')
            ->setParameter('archivee', StatutFiche::Archivee)
            ->orderBy('f.label', 'ASC');
    }

    public function findOneByFiche(Fiche $fiche): ?Restaurant
    {
        return $this->findOneBy(['fiche' => $fiche]);
    }

    /**
     * Lot de restaurants (fiche + localisation chargées) après un curseur ULID,
     * pour l'enrichissement d'attributs en masse. Keyset sur l'id.
     *
     * $sourceNonScanne exclut les restaurants déjà scannés « récemment » par
     * cette source (jamais scannés / modifiés depuis / périmés avant $seuil).
     *
     * @return list<Restaurant>
     */
    public function findBatchAfter(?string $afterId, int $limit, ?string $sourceNonScanne = null, ?\DateTimeImmutable $seuilFraicheur = null): array
    {
        $builder = $this->createQueryBuilder('r')
            ->addSelect('f', 'loc')
            ->join('r.fiche', 'f')
            ->leftJoin('f.localisation', 'loc')
            ->orderBy('r.id', 'ASC')
            ->setMaxResults(max(1, min(1000, $limit)));
        if (null !== $afterId && '' !== $afterId) {
            $builder->andWhere('r.id > :after')->setParameter('after', Ulid::fromString($afterId), 'ulid');
        }
        if (null !== $sourceNonScanne) {
            $builder
                ->andWhere('NOT EXISTS (SELECT 1 FROM App\Pim\Entity\FicheEnrichmentScan sc WHERE sc.fiche = f AND sc.source = :src AND sc.scannedAt >= f.updatedAt AND sc.scannedAt >= :seuil)')
                ->setParameter('src', $sourceNonScanne)
                ->setParameter('seuil', $seuilFraicheur ?? new \DateTimeImmutable('@0'));
        }

        /** @var list<Restaurant> $restaurants */
        $restaurants = $builder->getQuery()->getResult();

        return $restaurants;
    }

    public function findListPage(
        ?FicheCursor $cursor = null,
        int $limit = 50,
        ?StatutFiche $status = null,
    ): RestaurantListPage {
        $limit = max(1, min(100, $limit));
        $conditions = ['f.type = :type'];
        $parameters = ['type' => TypeFiche::Restaurant->value];
        $types = ['type' => ParameterType::STRING];

        if (null !== $status) {
            $conditions[] = 'f.status = :status';
            $parameters['status'] = $status->value;
            $types['status'] = ParameterType::STRING;
        }

        if (null !== $cursor) {
            $conditions[] = '(f.updated_at, f.id) < (:updated, :id)';
            $parameters['updated'] = $cursor->updatedAt->format('Y-m-d H:i:s');
            $parameters['id'] = $cursor->id->toBinary();
            $types['updated'] = ParameterType::STRING;
            $types['id'] = ParameterType::BINARY;
        }

        $sql = sprintf(
            'SELECT f.id, f.code, f.label, f.status, r.completeness_global AS completeness, f.updated_at, loc.ville '
            .'FROM pim_fiche f '
            .'INNER JOIN pim_restaurant r ON r.fiche_id = f.id '
            .'LEFT JOIN pim_localisation loc ON loc.id = f.localisation_id '
            .'WHERE %s '
            .'ORDER BY f.updated_at DESC, f.id DESC LIMIT %d',
            implode(' AND ', $conditions),
            $limit + 1,
        );
        $rows = $this->getEntityManager()
            ->getConnection()
            ->executeQuery($sql, $parameters, $types)
            ->fetchAllAssociative();
        $hasNext = count($rows) > $limit;
        $items = array_map(self::item(...), array_slice($rows, 0, $limit));
        $last = [] === $items ? null : $items[array_key_last($items)];

        return new RestaurantListPage(
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
                'SELECT COUNT(*) FROM pim_fiche f '
                .'INNER JOIN pim_restaurant r ON r.fiche_id = f.id '
                .'WHERE f.type = ? AND f.status = ?',
                [TypeFiche::Restaurant->value, $status->value],
            );
    }

    /** @param list<string> $ids
     *  @return list<RestaurantListItem>
     */
    public function findListItemsByIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        $rows = $this->getEntityManager()
            ->getConnection()
            ->executeQuery(
                'SELECT f.id, f.code, f.label, f.status, r.completeness_global AS completeness, f.updated_at, loc.ville '
                .'FROM pim_fiche f '
                .'INNER JOIN pim_restaurant r ON r.fiche_id = f.id '
                .'LEFT JOIN pim_localisation loc ON loc.id = f.localisation_id '
                .'WHERE f.type = :type AND f.id IN (:ids)',
                [
                    'type' => TypeFiche::Restaurant->value,
                    'ids' => array_map(
                        static fn (string $id): string =>
                            Ulid::fromString($id)->toBinary(),
                        $ids,
                    ),
                ],
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
                    static fn (string $id): ?RestaurantListItem =>
                        $mapped[$id] ?? null,
                    $ids,
                ),
            ),
        );
    }

    /** @param array<string, mixed> $row */
    private static function item(array $row): RestaurantListItem
    {
        return new RestaurantListItem(
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
