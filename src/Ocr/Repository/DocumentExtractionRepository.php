<?php

declare(strict_types=1);

namespace App\Ocr\Repository;

use App\Ocr\Entity\DocumentExtraction;
use App\Ocr\Enum\ExtractionStatus;
use App\Pim\Entity\Fiche;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<DocumentExtraction> */
final class DocumentExtractionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, DocumentExtraction::class); }

    /** @return list<DocumentExtraction> */
    public function history(Fiche $fiche): array
    {
        return $this->findBy(['fiche' => $fiche], ['createdAt' => 'DESC']);
    }

    public function findForFiche(string $id, Fiche $fiche): ?DocumentExtraction
    {
        return $this->findOneBy(['id' => $id, 'fiche' => $fiche]);
    }

    /** L'extraction non terminée de la fiche : une seule lecture à la fois. */
    public function enCours(Fiche $fiche): ?DocumentExtraction
    {
        return $this->findOneBy(
            ['fiche' => $fiche, 'status' => [ExtractionStatus::Queued, ExtractionStatus::Processing]],
            ['createdAt' => 'DESC'],
        );
    }

    /** La dernière extraction dont des valeurs restent à arbitrer. */
    public function aRevoir(Fiche $fiche): ?DocumentExtraction
    {
        return $this->findOneBy(
            ['fiche' => $fiche, 'status' => [ExtractionStatus::Ready, ExtractionStatus::PartiallyReviewed]],
            ['createdAt' => 'DESC'],
        );
    }
}
