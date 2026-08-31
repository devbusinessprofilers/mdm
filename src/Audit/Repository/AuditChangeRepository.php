<?php

declare(strict_types=1);

namespace App\Audit\Repository;

use App\Audit\Entity\AuditChange;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/** @extends ServiceEntityRepository<AuditChange> */
final class AuditChangeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditChange::class);
    }

    /**
     * Date de la dernière révision ayant touché chaque path pour une fiche —
     * la matière première de la règle de fusion « la valeur la plus récente
     * l'emporte ».
     *
     * @return array<string, \DateTimeImmutable> path => dernière modification
     */
    public function lastChangeDatesByPath(string $ficheId): array
    {
        /** @var list<array{path: string, lastAt: string}> $rows */
        $rows = $this->createQueryBuilder('c')
            ->select('c.path AS path', 'MAX(r.createdAt) AS lastAt')
            ->join('c.revision', 'r')
            ->andWhere('r.ficheId = :fiche')
            ->setParameter('fiche', Ulid::fromString($ficheId), 'ulid')
            ->groupBy('c.path')
            ->getQuery()
            ->getArrayResult();

        $dates = [];
        foreach ($rows as $row) {
            $dates[$row['path']] = new \DateTimeImmutable($row['lastAt']);
        }

        return $dates;
    }
}
