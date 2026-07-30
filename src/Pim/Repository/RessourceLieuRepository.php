<?php

declare(strict_types=1);

namespace App\Pim\Repository;

use App\Pim\Entity\Lieu\RessourceLieu;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<RessourceLieu> */
final class RessourceLieuRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RessourceLieu::class);
    }

    public function findOneByMediaId(string $mediaId): ?RessourceLieu
    {
        return $this->findOneBy(['damAssetId' => $mediaId]);
    }
}
