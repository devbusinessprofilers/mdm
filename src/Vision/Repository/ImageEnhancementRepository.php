<?php

declare(strict_types=1);

namespace App\Vision\Repository;

use App\Vision\Entity\ImageEnhancement;
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
    public function recent(int $page, int $perPage): array
    {
        return $this->findBy([], ['createdAt' => 'DESC'], $perPage, max(0, $page - 1) * $perPage);
    }

    public function countAll(): int
    {
        return $this->count([]);
    }

    public function countByStatus(EnhancementStatus $status): int
    {
        return $this->count(['status' => $status]);
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
