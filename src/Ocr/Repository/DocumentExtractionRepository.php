<?php

declare(strict_types=1);

namespace App\Ocr\Repository;

use App\Ocr\Entity\DocumentExtraction;
use App\Ocr\Entity\OcrSuggestion;
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

    /** Une suggestion précise, bornée à la fiche (décision en un clic). */
    public function suggestionPourFiche(string $suggestionId, Fiche $fiche): ?OcrSuggestion
    {
        $suggestion = $this->getEntityManager()
            ->getRepository(OcrSuggestion::class)
            ->findOneBy(['id' => $suggestionId]);

        return $suggestion instanceof OcrSuggestion
            && $suggestion->extraction()->fiche()->idString() === $fiche->idString()
            ? $suggestion
            : null;
    }

    /**
     * Suggestions encore en attente d'arbitrage sur la fiche (bloc
     * « Suggestions IA en attente »), les plus sûres d'abord.
     *
     * @return list<OcrSuggestion>
     */
    public function suggestionsEnAttente(Fiche $fiche): array
    {
        $extraction = $this->aRevoir($fiche);
        if (null === $extraction) {
            return [];
        }
        $pending = array_values(array_filter(
            $extraction->suggestions()->toArray(),
            static fn (OcrSuggestion $suggestion): bool => $suggestion->isPending(),
        ));
        usort($pending, static fn (OcrSuggestion $a, OcrSuggestion $b): int => ($b->confidence() ?? -1.0) <=> ($a->confidence() ?? -1.0));

        return $pending;
    }
}
