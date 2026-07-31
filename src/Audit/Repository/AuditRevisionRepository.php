<?php

declare(strict_types=1);

namespace App\Audit\Repository;

use App\Audit\Entity\AuditRevision;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/** @extends ServiceEntityRepository<AuditRevision> */
final class AuditRevisionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditRevision::class);
    }

    /** @param array<string, mixed> $filters
     * @return list<AuditRevision>
     */
    public function history(
        string $ficheId,
        ?string $cursor,
        int $limit,
        array $filters = [],
    ): array {
        $qb = $this->createQueryBuilder('r')
            ->where('r.ficheId = :fiche')
            ->setParameter('fiche', Ulid::fromString($ficheId), 'ulid')
            ->orderBy('r.createdAt', 'DESC')
            ->addOrderBy('r.id', 'DESC')
            ->setMaxResults($limit + 1);
        if (null !== $cursor && Ulid::isValid($cursor)) {
            $qb->andWhere('r.id < :cursor')->setParameter(
                'cursor',
                Ulid::fromString($cursor),
                'ulid',
            );
        }
        if (isset($filters['action']) && '' !== $filters['action']) {
            $qb->andWhere('r.action = :action')->setParameter(
                'action',
                $filters['action'],
            );
        }
        if (isset($filters['actor']) && '' !== $filters['actor']) {
            $qb->andWhere('r.actor LIKE :actor')->setParameter(
                'actor',
                '%'.$filters['actor'].'%',
            );
        }
        if (isset($filters['field']) && '' !== $filters['field']) {
            $qb->distinct()
                ->innerJoin('r.changes', 'filterChange')
                ->andWhere('filterChange.path LIKE :field')
                ->setParameter('field', '%'.$filters['field'].'%');
        }
        if (
            isset($filters['from'])
            && $filters['from'] instanceof \DateTimeInterface
        ) {
            $qb->andWhere('r.createdAt >= :from')->setParameter(
                'from',
                $filters['from'],
            );
        }
        if (
            isset($filters['to'])
            && $filters['to'] instanceof \DateTimeInterface
        ) {
            $qb->andWhere('r.createdAt <= :to')->setParameter(
                'to',
                $filters['to'],
            );
        }

        return $qb->getQuery()->getResult();
    }
}
