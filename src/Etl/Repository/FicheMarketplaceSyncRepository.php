<?php

declare(strict_types=1);

namespace App\Etl\Repository;

use App\Etl\Entity\FicheMarketplaceSync;
use App\Etl\Enum\MarketplaceSyncStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/** @extends ServiceEntityRepository<FicheMarketplaceSync> */
class FicheMarketplaceSyncRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FicheMarketplaceSync::class);
    }

    public function forFiche(Ulid $ficheId): ?FicheMarketplaceSync
    {
        return $this->find($ficheId);
    }

    /** @return list<FicheMarketplaceSync> */
    public function findFailed(int $limit = 500): array
    {
        return $this->findBy(
            ['status' => MarketplaceSyncStatus::Failed],
            ['updatedAt' => 'ASC'],
            $limit,
        );
    }
}
