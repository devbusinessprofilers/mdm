<?php

declare(strict_types=1);

namespace App\Dashboard\Service;

use App\Audit\Entity\AuditChange;
use App\Audit\Entity\AuditRevision;
use App\Pim\Entity\Fiche;
use App\Pim\Enum\StatutFiche;
use App\Pim\Enum\TypeFiche;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DashboardStatsCalculator
{
    private const string SYSTEM_ACTOR = 'system';

    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /** @return array<string, mixed> */
    public function compute(): array
    {
        // Semaine calendaire parisienne, convertie en UTC (fuseau de stockage).
        $weekStart = new \DateTimeImmutable(
            'monday this week midnight',
            new \DateTimeZone('Europe/Paris'),
        );
        $weekStartUtc = $weekStart->setTimezone(new \DateTimeZone('UTC'));

        return [
            'fiches' => $this->ficheTotals(),
            'completeness' => $this->completeness(),
            'storage' => $this->storage(),
            'countryByType' => $this->countryByType(),
            'validation' => $this->validationDelay(),
            'thisWeek' => $this->thisWeek($weekStartUtc, $weekStart),
            'perUser' => [
                'created' => $this->countRevisionsByActor(['create']),
                'fieldsUpdated' => $this->countChangesByActor(),
                'validated' => $this->countRevisionsByActor(['publication']),
            ],
        ];
    }

    /** @return array{total: int, published: int, publishedPct: float} */
    private function ficheTotals(): array
    {
        /** @var array{total: string|int, published: string|int|null} $row */
        $row = $this->entityManager->createQuery(
            'SELECT COUNT(f.id) AS total,'
            .' SUM(CASE WHEN f.status = :published THEN 1 ELSE 0 END) AS published'
            .' FROM '.Fiche::class.' f',
        )->setParameter('published', StatutFiche::Publiee)->getSingleResult();
        $total = (int) $row['total'];
        $published = (int) $row['published'];

        return [
            'total' => $total,
            'published' => $published,
            'publishedPct' => 0 === $total
                ? 0.0
                : round(100 * $published / $total, 1),
        ];
    }

    /** @return array{avgGlobal: float|null} */
    private function completeness(): array
    {
        // Les scores vivent sur les entités de détail, chacune dans sa table.
        $avg = $this->entityManager->getConnection()->fetchOne(
            'SELECT AVG(completeness_global) FROM ('
            .' SELECT completeness_global FROM pim_lieu'
            .' UNION ALL SELECT completeness_global FROM pim_restaurant'
            .' UNION ALL SELECT completeness_global FROM pim_activite'
            .' UNION ALL SELECT completeness_global FROM pim_service_evenementiel'
            .') scores',
        );

        return ['avgGlobal' => null === $avg ? null : round((float) $avg, 1)];
    }

    /**
     * @return array{
     *     byKind: array<string, array{count: int, bytes: int}>,
     *     renditions: array{count: int, bytes: int},
     *     totalBytes: int,
     * }
     */
    private function storage(): array
    {
        $connection = $this->entityManager->getConnection();
        /** @var list<array{kind: string, nb: string|int, bytes: string|int|null}> $rows */
        $rows = $connection->fetchAllAssociative(
            'SELECT kind, COUNT(*) AS nb, COALESCE(SUM(size_bytes), 0) AS bytes'
            ." FROM dam_media_asset WHERE status NOT IN ('deleting', 'deleted')"
            .' GROUP BY kind',
        );
        $byKind = [];
        $totalBytes = 0;
        foreach ($rows as $row) {
            $byKind[$row['kind']] = ['count' => (int) $row['nb'], 'bytes' => (int) $row['bytes']];
            $totalBytes += (int) $row['bytes'];
        }
        /** @var array{nb: string|int, bytes: string|int|null} $renditions */
        $renditions = $connection->fetchAssociative(
            'SELECT COUNT(*) AS nb, COALESCE(SUM(size_bytes), 0) AS bytes FROM dam_media_rendition',
        ) ?: ['nb' => 0, 'bytes' => 0];

        return [
            'byKind' => $byKind,
            'renditions' => ['count' => (int) $renditions['nb'], 'bytes' => (int) $renditions['bytes']],
            'totalBytes' => $totalBytes + (int) $renditions['bytes'],
        ];
    }

    /**
     * @return list<array{
     *     countryCode: string,
     *     pays: string,
     *     counts: array<string, int>,
     *     total: int,
     * }>
     */
    private function countryByType(): array
    {
        /** @var list<array{cc: string, pays: string, type: string, nb: string|int}> $rows */
        $rows = $this->entityManager->createQuery(
            'SELECT COALESCE(l.countryCode, \'??\') AS cc,'
            .' COALESCE(l.pays, \'Non renseigné\') AS pays,'
            .' f.type AS type, COUNT(f.id) AS nb'
            .' FROM '.Fiche::class.' f LEFT JOIN f.localisation l'
            .' GROUP BY cc, pays, type',
        )->getResult();
        $emptyCounts = array_fill_keys(
            array_map(
                static fn (TypeFiche $type): string => $type->value,
                TypeFiche::cases(),
            ),
            0,
        );
        $countries = [];
        foreach ($rows as $row) {
            $key = $row['cc'].'|'.$row['pays'];
            $countries[$key] ??= [
                'countryCode' => $row['cc'],
                'pays' => $row['pays'],
                'counts' => $emptyCounts,
                'total' => 0,
            ];
            $type = $row['type'] instanceof TypeFiche ? $row['type']->value : (string) $row['type'];
            $countries[$key]['counts'][$type] = (int) $row['nb'];
            $countries[$key]['total'] += (int) $row['nb'];
        }
        usort(
            $countries,
            static fn (array $a, array $b): int => [$b['total'], $a['pays']] <=> [$a['total'], $b['pays']],
        );

        return array_values($countries);
    }

    /**
     * Moyenne sur le dernier cycle de validation de chaque fiche
     * (les horodatages sont écrasés à chaque nouveau cycle).
     *
     * @return array{avgSeconds: int|null, sample: int}
     */
    private function validationDelay(): array
    {
        /** @var array{0: string|float|null, 1: string|int}|false $row */
        $row = $this->entityManager->getConnection()->fetchNumeric(
            'SELECT AVG(TIMESTAMPDIFF(SECOND, validation_requested_at, validation_reviewed_at)), COUNT(*)'
            .' FROM pim_fiche'
            .' WHERE validation_requested_at IS NOT NULL'
            .' AND validation_reviewed_at IS NOT NULL'
            .' AND validation_reviewed_at >= validation_requested_at',
        );
        if (false === $row || null === $row[0]) {
            return ['avgSeconds' => null, 'sample' => 0];
        }

        return [
            'avgSeconds' => (int) round((float) $row[0]),
            'sample' => (int) $row[1],
        ];
    }

    /** @return array{updates: int, created: int, weekStart: string} */
    private function thisWeek(
        \DateTimeImmutable $weekStart,
        \DateTimeImmutable $weekStartLocal,
    ): array {
        $updates = $this->entityManager->createQuery(
            'SELECT COUNT(r.id) FROM '.AuditRevision::class.' r'
            .' WHERE r.action = :action AND r.createdAt >= :weekStart',
        )
            ->setParameter('action', 'update')
            ->setParameter('weekStart', $weekStart)
            ->getSingleScalarResult();
        $created = $this->entityManager->createQuery(
            'SELECT COUNT(f.id) FROM '.Fiche::class.' f'
            .' WHERE f.createdAt >= :weekStart',
        )->setParameter('weekStart', $weekStart)->getSingleScalarResult();

        return [
            'updates' => (int) $updates,
            'created' => (int) $created,
            'weekStart' => $weekStartLocal->format('Y-m-d'),
        ];
    }

    /**
     * @param list<string> $actions
     *
     * @return list<array{actor: string, count: int}>
     */
    private function countRevisionsByActor(array $actions): array
    {
        /** @var list<array{actor: string, nb: string|int}> $rows */
        $rows = $this->entityManager->createQuery(
            'SELECT r.actor AS actor, COUNT(r.id) AS nb'
            .' FROM '.AuditRevision::class.' r'
            .' WHERE r.action IN (:actions) AND r.actor <> :system'
            .' GROUP BY r.actor ORDER BY nb DESC, actor ASC',
        )
            ->setParameter('actions', $actions)
            ->setParameter('system', self::SYSTEM_ACTOR)
            ->getResult();

        return array_map(
            static fn (array $row): array => [
                'actor' => $row['actor'],
                'count' => (int) $row['nb'],
            ],
            $rows,
        );
    }

    /** @return list<array{actor: string, count: int}> */
    private function countChangesByActor(): array
    {
        /** @var list<array{actor: string, nb: string|int}> $rows */
        $rows = $this->entityManager->createQuery(
            'SELECT r.actor AS actor, COUNT(c.id) AS nb'
            .' FROM '.AuditChange::class.' c JOIN c.revision r'
            .' WHERE r.action = :action AND r.actor <> :system'
            .' GROUP BY r.actor ORDER BY nb DESC, actor ASC',
        )
            ->setParameter('action', 'update')
            ->setParameter('system', self::SYSTEM_ACTOR)
            ->getResult();

        return array_map(
            static fn (array $row): array => [
                'actor' => $row['actor'],
                'count' => (int) $row['nb'],
            ],
            $rows,
        );
    }
}
