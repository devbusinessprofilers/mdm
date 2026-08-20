<?php

declare(strict_types=1);

namespace App\Vision\Repository;

use App\Pim\Entity\Lieu\RessourceLieu;
use App\Vision\Entity\ImageRecognition;
use App\Vision\Enum\RecognitionStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ImageRecognition> */
final class ImageRecognitionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ImageRecognition::class);
    }

    /** Une seule analyse à la fois par ressource : file ou review en cours. */
    public function hasActiveForResource(RessourceLieu $resource): bool
    {
        return null !== $this->findOneBy([
            'resource' => $resource,
            'status' => [RecognitionStatus::Queued, RecognitionStatus::Processing, RecognitionStatus::Ready],
        ]);
    }

    /** @return list<ImageRecognition> */
    public function recent(int $page, int $perPage): array
    {
        return $this->findBy([], ['createdAt' => 'DESC'], $perPage, max(0, $page - 1) * $perPage);
    }

    public function countAll(): int
    {
        return $this->count([]);
    }

    public function countAwaitingReview(): int
    {
        return $this->count(['status' => [RecognitionStatus::Ready, RecognitionStatus::PartiallyReviewed]]);
    }
}
