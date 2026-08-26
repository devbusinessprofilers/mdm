<?php

declare(strict_types=1);

namespace App\Pim\Repository;

use App\Pim\Entity\FicheCollaborateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<FicheCollaborateur> */
final class FicheCollaborateurRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, FicheCollaborateur::class); }
    public function findOneByEmail(string $email): ?FicheCollaborateur { return $this->findOneBy(['email' => FicheCollaborateur::normalizeEmail($email)]); }

    /** @return list<FicheCollaborateur> */
    public function findPage(int $limit, int $offset): array { return $this->findBy([], ['email' => 'ASC'], $limit, $offset); }
    public function countAll(): int { return $this->count([]); }

    /** @return list<FicheCollaborateur> */
    public function searchPage(string $q, int $limit, int $offset): array
    {
        /** @var list<FicheCollaborateur> */
        return $this->createSearchQueryBuilder($q)
            ->orderBy('c.email', 'ASC')
            ->setMaxResults($limit)->setFirstResult($offset)
            ->getQuery()->getResult();
    }

    public function countSearch(string $q): int
    {
        return (int) $this->createSearchQueryBuilder($q)
            ->select('COUNT(c.id)')
            ->getQuery()->getSingleScalarResult();
    }

    private function createSearchQueryBuilder(string $q): \Doctrine\ORM\QueryBuilder
    {
        $builder = $this->createQueryBuilder('c');
        if ('' !== $q) {
            $builder
                ->andWhere('c.email LIKE :like OR c.firstName LIKE :like OR c.lastName LIKE :like')
                ->setParameter('like', '%'.addcslashes($q, '%_\\').'%');
        }

        return $builder;
    }
}
