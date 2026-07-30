<?php

declare(strict_types=1);

namespace App\Dam\Repository;

use App\Dam\Entity\MediaAsset;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/** @extends ServiceEntityRepository<MediaAsset> */
final class MediaAssetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MediaAsset::class);
    }

    /**
     * @param list<string> $ids
     * @return list<MediaAsset>
     */
    public function findByStringIds(array $ids): array
    {
        $ulids = array_map(
            static fn (string $id): Ulid => Ulid::fromString($id),
            array_values(array_filter($ids, Ulid::isValid(...))),
        );

        return [] === $ulids ? [] : $this->findBy(['id' => $ulids]);
    }
}
