<?php

declare(strict_types=1);

namespace App\Pim\Repository;

use App\Pim\Entity\Fiche;
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
                l.completeness_global AS completeness,
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

        /** @var list<array{id: string, code: int|string, label: string|null, status: string, completeness: int|string, updated_at: string, ville: string|null}> $rows */
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

    public function findOneByFicheWithLocalisation(Fiche $fiche): ?Lieu
    {
        // Fetch-join the inverse one-to-one associations as well: Doctrine
        // otherwise resolves them with two additional queries while hydrating.
        return $this->createQueryBuilder('l')
            ->addSelect('f', 'loc', 'administratif', 'tarification')
            ->innerJoin('l.fiche', 'f')
            ->leftJoin('f.localisation', 'loc')
            ->leftJoin('l.administratif', 'administratif')
            ->leftJoin('l.tarification', 'tarification')
            ->where('l.fiche = :fiche')
            // DQL does not run the 'ulid' column type on inferred parameters; bind it explicitly.
            ->setParameter('fiche', $fiche->id(), 'ulid')
            ->getQuery()
            ->getOneOrNullResult();
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
        /** @var list<array{id: string, code: int|string, label: string|null, status: string, completeness: int|string, updated_at: string, ville: string|null}> $rows */
        $rows = $this->getEntityManager()->getConnection()->executeQuery(<<<'SQL'
            SELECT
                f.id,
                f.code,
                f.label,
                f.status,
                l.completeness_global AS completeness,
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

    /** @return list<string> identifiants ULID des fiches Lieu dont le SIRET correspond */
    public function findFicheIdsBySiret(string $siret): array
    {
        $siret = preg_replace('/\s+/', '', trim($siret)) ?? '';
        if ('' === $siret) {
            return [];
        }
        $rows = $this->getEntityManager()->getConnection()->fetchFirstColumn(
            "SELECT l.fiche_id FROM pim_lieu l
             INNER JOIN pim_lieu_administratif a ON a.lieu_id = l.id
             WHERE REPLACE(a.info_legale_siret, ' ', '') = :siret",
            ['siret' => $siret],
            ['siret' => ParameterType::STRING],
        );

        return array_map(static fn (string $id): string => (string) Ulid::fromBinary($id), $rows);
    }

    /** Le lieu porteur d'une fiche. */
    public function findOneByFiche(Fiche $fiche): ?Lieu
    {
        return $this->findOneBy(['fiche' => $fiche]);
    }

    /**
     * Lot de lieux (fiche + administratif + localisation chargés) après un
     * curseur ULID, pour la vérification de statut en masse. Le keyset sur l'id
     * évite l'OFFSET lent ; plafond aligné sur les autres itérateurs.
     *
     * $sourceNonScanne exclut les lieux déjà scannés « récemment » par cette
     * source : on ne garde que les jamais scannés, ceux modifiés depuis le scan
     * (scanned_at < updated_at) ou scannés avant $seuilFraicheur.
     *
     * @return list<Lieu>
     */
    public function findBatchAfter(?string $afterId, int $limit, ?string $sourceNonScanne = null, ?\DateTimeImmutable $seuilFraicheur = null): array
    {
        $builder = $this->createQueryBuilder('l')
            ->addSelect('f', 'a', 'loc')
            ->join('l.fiche', 'f')
            ->join('l.administratif', 'a')
            ->leftJoin('f.localisation', 'loc')
            ->orderBy('l.id', 'ASC')
            ->setMaxResults(max(1, min(1000, $limit)));
        if (null !== $afterId && '' !== $afterId) {
            $builder->andWhere('l.id > :after')->setParameter('after', Ulid::fromString($afterId), 'ulid');
        }
        if (null !== $sourceNonScanne) {
            $builder
                ->andWhere('NOT EXISTS (SELECT 1 FROM App\Pim\Entity\FicheEnrichmentScan sc WHERE sc.fiche = f AND sc.source = :src AND sc.scannedAt >= f.updatedAt AND sc.scannedAt >= :seuil)')
                ->setParameter('src', $sourceNonScanne)
                ->setParameter('seuil', $seuilFraicheur ?? new \DateTimeImmutable('@0'));
        }

        /** @var list<Lieu> $lieux */
        $lieux = $builder->getQuery()->getResult();

        return $lieux;
    }

    /** @param array{id: string, code: int|string, label: string|null, status: string, completeness: int|string, updated_at: string, ville: string|null} $row */
    private static function toListItem(array $row): LieuListItem
    {
        return new LieuListItem(
            id: (string) Ulid::fromBinary($row['id']),
            code: (int) $row['code'],
            label: $row['label'],
            ville: $row['ville'],
            status: StatutFiche::from($row['status']),
            completeness: (int) $row['completeness'],
            updatedAt: new \DateTimeImmutable($row['updated_at']),
        );
    }
}
