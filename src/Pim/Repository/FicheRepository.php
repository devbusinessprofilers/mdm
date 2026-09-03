<?php

declare(strict_types=1);

namespace App\Pim\Repository;

use App\Pim\Entity\Fiche;
use App\Pim\Enum\StatutFiche;
use App\Pim\Enum\TypeFiche;
use App\Pim\ReadModel\FicheCursor;
use App\Pim\ReadModel\FicheListPage;
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

    /** La fiche d'un identifiant ULID reçu en chaîne (message, requête) ; null si l'identifiant est malformé ou inconnu. */
    public function parId(string $id): ?Fiche
    {
        if (!Ulid::isValid($id)) {
            return null;
        }
        $fiche = $this->find(Ulid::fromString($id));

        return $fiche instanceof Fiche ? $fiche : null;
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

    /**
     * @param list<Ulid> $ids
     *
     * @return list<Fiche> Fiches du lot avec leurs sélections de sites déjà
     *                     chargées : évite un chargement de collection par fiche
     */
    public function findByIdsAvecSiteSelections(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }
        /** @var list<Fiche> $fiches */
        $fiches = $this->createQueryBuilder('fiche')
            ->leftJoin('fiche.siteSelections', 'selection')
            ->addSelect('selection')
            ->andWhere('fiche.id IN (:ids)')
            // En DQL, les Ulid ne passent pas par le type de colonne : binaire explicite.
            ->setParameter('ids', array_map(static fn (Ulid $id): string => $id->toBinary(), $ids), ArrayParameterType::BINARY)
            ->getQuery()
            ->getResult();

        return $fiches;
    }

    /** @return list<Ulid> Ids des fiches non archivées, ordonnés (parcours par lots). */
    public function findIdsNonArchivees(): array
    {
        /** @var list<array{id: Ulid}> $lignes */
        $lignes = $this->createQueryBuilder('fiche')
            ->select('fiche.id')
            ->andWhere('fiche.status != :archivee')
            ->setParameter('archivee', StatutFiche::Archivee)
            ->orderBy('fiche.id', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $ligne): Ulid => $ligne['id'], $lignes);
    }

    /** @return list<Fiche> Dernières fiches publiées, les plus récentes d'abord. */
    public function findDernieresPubliees(int $limit): array
    {
        /** @var list<Fiche> $fiches */
        $fiches = $this->createQueryBuilder('fiche')
            ->andWhere('fiche.status = :status')
            ->setParameter('status', StatutFiche::Publiee)
            ->andWhere('fiche.publishedAt IS NOT NULL')
            ->orderBy('fiche.publishedAt', 'DESC')
            ->addOrderBy('fiche.id', 'DESC')
            ->setMaxResults(max(1, min(20, $limit)))
            ->getQuery()
            ->getResult();

        return $fiches;
    }

    /**
     * Fiches avec localisation, tous statuts, paginées par identifiant
     * (parcours par lots des commandes de normalisation d'adresses).
     *
     * @return list<Fiche>
     */
    public function findWithLocalisationAfter(?string $afterId, int $limit): array
    {
        $builder = $this->createQueryBuilder('fiche')
            ->addSelect('localisation')
            ->join('fiche.localisation', 'localisation')
            ->orderBy('fiche.id', 'ASC')
            ->setMaxResults(max(1, min(1000, $limit)));
        if (null !== $afterId && '' !== $afterId) {
            $builder->andWhere('fiche.id > :after')->setParameter('after', Ulid::fromString($afterId), 'ulid');
        }

        /** @var list<Fiche> $fiches */
        $fiches = $builder->getQuery()->getResult();

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
     * @param list<string> $ids ordered fiche ULIDs, in search relevance order
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
            $item = self::toSearchItem($row);
            $itemsById[$item->id] = $item;
        }

        return array_values(array_filter(array_map(
            static fn (string $id): ?GlobalSearchItem => $itemsById[$id] ?? null,
            $ids,
        )));
    }

    /** @return list<string> identifiants ULID des fiches portant exactement ce libellé */
    public function findIdsByLabelInsensitive(string $label): array
    {
        $rows = $this->getEntityManager()->getConnection()->fetchFirstColumn(
            'SELECT id FROM pim_fiche WHERE LOWER(label) = LOWER(:label)',
            ['label' => trim($label)],
            ['label' => ParameterType::STRING],
        );

        return array_map(static fn (string $id): string => (string) Ulid::fromBinary($id), $rows);
    }

    /**
     * @param list<string> $fingerprints empreintes binaires (Localisation::addressFingerprint)
     *
     * @return list<string> identifiants ULID des fiches dont la localisation partage une empreinte
     */
    public function findIdsByAddressFingerprints(array $fingerprints): array
    {
        if ([] === $fingerprints) {
            return [];
        }
        $rows = $this->getEntityManager()->getConnection()->fetchFirstColumn(
            'SELECT f.id FROM pim_fiche f
             INNER JOIN pim_localisation loc ON loc.id = f.localisation_id
             WHERE loc.address_fingerprint IN (:fingerprints)',
            ['fingerprints' => $fingerprints],
            ['fingerprints' => ArrayParameterType::BINARY],
        );

        return array_map(static fn (string $id): string => (string) Ulid::fromBinary($id), $rows);
    }

    /**
     * @param list<string> $ids identifiants ULID
     *
     * @return array<string, string> valeur TypeFiche par identifiant ULID, sans hydrater d'entité
     */
    public function findTypesByIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }
        $rows = $this->getEntityManager()->getConnection()->fetchAllKeyValue(
            'SELECT id, type FROM pim_fiche WHERE id IN (:ids)',
            ['ids' => array_map(static fn (string $id): string => Ulid::fromString($id)->toBinary(), $ids)],
            ['ids' => ArrayParameterType::BINARY],
        );

        $types = [];
        foreach ($rows as $id => $type) {
            $types[(string) Ulid::fromBinary($id)] = $type;
        }

        return $types;
    }

    public function findAllListPage(
        ?FicheCursor $cursor = null,
        int $limit = 50,
        ?TypeFiche $type = null,
        ?StatutFiche $status = null,
        ?string $countryCode = null,
        ?int $completenessMin = null,
        ?int $completenessMax = null,
    ): FicheListPage {
        $limit = max(1, min(100, $limit));
        $conditions = ['1 = 1'];
        $parameters = [];
        $types = [];

        if (null !== $type) {
            $conditions[] = 'f.type = :type';
            $parameters['type'] = $type->value;
            $types['type'] = ParameterType::STRING;
        }
        if (null !== $status) {
            $conditions[] = 'f.status = :status';
            $parameters['status'] = $status->value;
            $types['status'] = ParameterType::STRING;
        }
        $localisationJoin = 'LEFT JOIN pim_localisation loc ON loc.id = f.localisation_id';
        if (null !== $countryCode) {
            $localisationJoin = 'INNER JOIN pim_localisation loc ON loc.id = f.localisation_id';
            $conditions[] = 'loc.country_code = :country';
            $parameters['country'] = $countryCode;
            $types['country'] = ParameterType::STRING;
        }
        $completenessSql = <<<'SQL'
            CASE f.type
                WHEN 'lieu' THEN l.completeness_global
                WHEN 'activite' THEN a.completeness_global
                WHEN 'restaurant' THEN r.completeness_global
                WHEN 'service_evenementiel' THEN s.completeness_global
                ELSE 0
            END
            SQL;
        if (null !== $completenessMin) {
            $conditions[] = 'COALESCE('.$completenessSql.', 0) >= :completeness_min';
            $parameters['completeness_min'] = $completenessMin;
            $types['completeness_min'] = ParameterType::INTEGER;
        }
        if (null !== $completenessMax) {
            $conditions[] = 'COALESCE('.$completenessSql.', 0) <= :completeness_max';
            $parameters['completeness_max'] = $completenessMax;
            $types['completeness_max'] = ParameterType::INTEGER;
        }
        if (null !== $cursor) {
            $conditions[] = '(f.updated_at, f.id) < (:cursor_updated_at, :cursor_id)';
            $parameters['cursor_updated_at'] = $cursor->updatedAt->format('Y-m-d H:i:s');
            $parameters['cursor_id'] = $cursor->id->toBinary();
            $types['cursor_updated_at'] = ParameterType::STRING;
            $types['cursor_id'] = ParameterType::BINARY;
        }

        // MariaDB otherwise tends to start with the type tables and filesort the
        // result; keeping pim_fiche first lets the keyset indexes satisfy the ordering.
        $sql = sprintf(<<<'SQL'
            SELECT STRAIGHT_JOIN
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
                %s AS completeness,
                f.updated_at
            FROM pim_fiche f
            LEFT JOIN pim_lieu l ON l.fiche_id = f.id AND f.type = 'lieu'
            LEFT JOIN pim_activite a ON a.fiche_id = f.id AND f.type = 'activite'
            LEFT JOIN pim_restaurant r ON r.fiche_id = f.id AND f.type = 'restaurant'
            LEFT JOIN pim_service_evenementiel s ON s.fiche_id = f.id AND f.type = 'service_evenementiel'
            %s
            WHERE %s
            ORDER BY f.updated_at DESC, f.id DESC
            LIMIT %d
            SQL,
            $completenessSql,
            $localisationJoin,
            implode("\n  AND ", $conditions),
            $limit + 1,
        );

        /** @var list<array{id: string, type: string, code: int|string, label: string|null, ville: string|null, status: string, completeness: int|string|null, updated_at: string}> $rows */
        $rows = $this->getEntityManager()->getConnection()->executeQuery($sql, $parameters, $types)->fetchAllAssociative();
        $hasNext = count($rows) > $limit;
        $rows = array_slice($rows, 0, $limit);
        $items = array_map(self::toSearchItem(...), $rows);
        $last = [] === $items ? null : $items[array_key_last($items)];

        return new FicheListPage(
            $items,
            $hasNext && null !== $last ? (new FicheCursor($last->updatedAt, Ulid::fromString($last->id)))->encode() : null,
        );
    }

    public function countAll(): int
    {
        return (int) $this->getEntityManager()->getConnection()->fetchOne('SELECT COUNT(*) FROM pim_fiche');
    }

    public function countPublishedWithoutPhoto(?TypeFiche $type = null): int
    {
        return (int) $this->publishedWithoutPhotoQuery($type)
            ->select('COUNT(fiche.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<Fiche> */
    public function findPublishedWithoutPhotoPage(?TypeFiche $type, int $page, int $limit): array
    {
        return $this->publishedWithoutPhotoQuery($type)
            ->orderBy('fiche.updatedAt', 'DESC')
            ->addOrderBy('fiche.id', 'DESC')
            ->setFirstResult((max(1, $page) - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    private function publishedWithoutPhotoQuery(?TypeFiche $type): \Doctrine\ORM\QueryBuilder
    {
        $builder = $this->createQueryBuilder('fiche')
            ->where('fiche.status = :published')
            ->andWhere(sprintf(
                'NOT EXISTS (SELECT resource.id FROM %s resource WHERE resource.fiche = fiche AND resource.nature = :photo)',
                \App\Pim\Entity\Lieu\RessourceLieu::class,
            ))
            ->setParameter('published', StatutFiche::Publiee)
            ->setParameter('photo', \App\Pim\Enum\NatureRessource::Photo);
        if (null !== $type) {
            $builder->andWhere('fiche.type = :type')->setParameter('type', $type);
        }

        return $builder;
    }

    /** @param array{id: string, type: string, code: int|string, label: string|null, ville: string|null, status: string, completeness: int|string|null, updated_at: string} $row */
    private static function toSearchItem(array $row): GlobalSearchItem
    {
        return new GlobalSearchItem(
            id: (string) Ulid::fromBinary($row['id']),
            type: TypeFiche::from($row['type']),
            code: (int) $row['code'],
            label: $row['label'],
            ville: $row['ville'],
            status: StatutFiche::from($row['status']),
            completeness: (int) $row['completeness'],
            updatedAt: new \DateTimeImmutable($row['updated_at']),
        );
    }
}
