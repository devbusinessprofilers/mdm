<?php

declare(strict_types=1);

namespace App\Pim\Repository;

use App\Pim\Entity\SavedView;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<SavedView> */
class SavedViewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SavedView::class);
    }

    /** @return list<SavedView> Vues personnelles de l'utilisateur puis vues partagées par l'équipe. */
    public function findVisiblesPour(string $userId): array
    {
        return $this->createQueryBuilder('v')
            ->where('v.ownerId = :owner OR v.shared = true')
            ->setParameter('owner', $userId)
            ->orderBy('v.shared', 'ASC')
            ->addOrderBy('v.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
