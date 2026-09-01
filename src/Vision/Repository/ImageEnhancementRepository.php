<?php

declare(strict_types=1);

namespace App\Vision\Repository;

use App\Vision\Entity\ImageEnhancement;
use App\Vision\Enum\EnhancementProvider;
use App\Vision\Enum\EnhancementStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ImageEnhancement> */
final class ImageEnhancementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ImageEnhancement::class);
    }

    /** Un job encore en file (ou en attente de décision) occupe le média. */
    public function hasActiveForMedia(string $mediaId): bool
    {
        return null !== $this->findOneBy([
            'media' => $mediaId,
            'status' => [EnhancementStatus::Queued, EnhancementStatus::Processing, EnhancementStatus::Ready],
        ]);
    }

    /** @return list<ImageEnhancement> */
    public function recent(int $page, int $perPage, ?EnhancementProvider $provider = null): array
    {
        $criteria = null === $provider ? [] : ['provider' => $provider];

        return $this->findBy($criteria, ['createdAt' => 'DESC'], $perPage, max(0, $page - 1) * $perPage);
    }

    public function countAll(?EnhancementProvider $provider = null): int
    {
        return $this->count(null === $provider ? [] : ['provider' => $provider]);
    }

    public function countByStatus(EnhancementStatus ...$statuses): int
    {
        return $this->count(['status' => $statuses]);
    }

    /** La retouche acceptée dont le résultat est la source actuelle du média. */
    public function acceptedForMedia(string $mediaId): ?ImageEnhancement
    {
        return $this->findOneBy(
            ['media' => $mediaId, 'status' => EnhancementStatus::Accepted],
            ['decidedAt' => 'DESC'],
        );
    }
}
