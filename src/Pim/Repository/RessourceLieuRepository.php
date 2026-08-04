<?php

declare(strict_types=1);

namespace App\Pim\Repository;

use App\Dam\Enum\DocumentUsage;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Enum\NatureRessource;
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

    public function findDocumentForFiche(Fiche $fiche, string $id, ?DocumentUsage $usage = null): ?RessourceLieu
    {
        $criteria = ['id' => $id, 'fiche' => $fiche, 'nature' => NatureRessource::Document];
        if (null !== $usage) { $criteria['documentUsage'] = $usage; }

        return $this->findOneBy($criteria);
    }

    public function findPhotoForFiche(Fiche $fiche, string $id): ?RessourceLieu
    {
        return $this->findOneBy(['id' => $id, 'fiche' => $fiche, 'nature' => NatureRessource::Photo]);
    }
}
