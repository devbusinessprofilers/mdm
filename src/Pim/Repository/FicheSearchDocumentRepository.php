<?php

declare(strict_types=1);

namespace App\Pim\Repository;

use App\Pim\Entity\Fiche;
use App\Pim\Entity\FicheSearchDocument;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<FicheSearchDocument> */
final class FicheSearchDocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FicheSearchDocument::class);
    }

    public function findForFiche(Fiche $fiche): ?FicheSearchDocument
    {
        return $this->find($fiche);
    }
}
