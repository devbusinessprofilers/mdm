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

    public function countByStatus(RecognitionStatus ...$statuses): int
    {
        return $this->count(['status' => $statuses]);
    }

    /**
     * Photos sans mots-clés ni analyse active : l'assiette du lancement en
     * masse. Le NOT EXISTS écarte les photos déjà en file ou en revue, sinon
     * chaque vague relirait les mêmes premières photos tant que leurs
     * suggestions ne sont pas validées.
     *
     * @return list<RessourceLieu>
     */
    public function findPhotosSansMotsClesSansAnalyse(int $limit, int $offset = 0): array
    {
        return $this->photosSansMotsClesSansAnalyseQuery()
            ->select('resource')
            ->orderBy('resource.id', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }

    public function countPhotosSansMotsClesSansAnalyse(): int
    {
        return (int) $this->photosSansMotsClesSansAnalyseQuery()
            ->select('COUNT(resource.id)')
            ->getQuery()->getSingleScalarResult();
    }

    private function photosSansMotsClesSansAnalyseQuery(): \Doctrine\ORM\QueryBuilder
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->from(RessourceLieu::class, 'resource')
            ->join('resource.fiche', 'fiche')
            ->andWhere('resource.nature = :photo')
            ->andWhere("resource.damAssetId <> ''")
            ->andWhere("resource.keywords IS NULL OR TRIM(resource.keywords) = ''")
            ->andWhere(sprintf(
                'NOT EXISTS (SELECT reco.id FROM %s reco WHERE reco.resource = resource AND reco.status IN (:actifs))',
                ImageRecognition::class,
            ))
            ->setParameter('photo', \App\Pim\Enum\NatureRessource::Photo)
            ->setParameter('actifs', [RecognitionStatus::Queued, RecognitionStatus::Processing, RecognitionStatus::Ready]);
    }
}
