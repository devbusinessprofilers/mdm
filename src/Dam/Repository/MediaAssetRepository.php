<?php

declare(strict_types=1);

namespace App\Dam\Repository;

use App\Dam\Entity\MediaAsset;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
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
     *
     * @return list<MediaAsset>
     */
    public function findByStringIds(array $ids): array
    {
        $ulids = array_map(
            static fn (string $id): Ulid => Ulid::fromString($id),
            array_values(array_filter($ids, Ulid::isValid(...))),
        );
        if ([] === $ulids) {
            return [];
        }

        // Fetch-join renditions so presenters can resolve variant URLs
        // without one lazy collection load per asset.
        return $this->createQueryBuilder('m')
            ->addSelect('r')
            ->leftJoin('m.renditions', 'r')
            ->where('m.id IN (:ids)')
            ->setParameter(
                'ids',
                array_map(
                    static fn (Ulid $id): string => $id->toBinary(),
                    $ulids,
                ),
                ArrayParameterType::BINARY,
            )
            ->getQuery()
            ->getResult();
    }
}
