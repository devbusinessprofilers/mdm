<?php

declare(strict_types=1);

namespace App\Ocr\Service;

use App\Dam\Enum\DocumentUsage;
use App\Dam\Service\FicheDocumentUploader;
use App\Ocr\Catalog\OcrFieldCatalog;
use App\Ocr\Entity\DocumentExtraction;
use App\Ocr\Message\ExtractDocument;
use App\Ocr\Repository\DocumentExtractionRepository;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Enum\NatureRessource;
use App\Shared\Outbox\OutboxPublisherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class OcrExtractionManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private FicheDocumentUploader $uploader,
        private PdfDocumentProcessor $pdf,
        private OcrCategoryPolicy $categories,
        private OcrFieldCatalog $catalog,
        private OutboxPublisherInterface $outbox,
        private DocumentExtractionRepository $extractions,
    ) {
    }

    public function upload(Fiche $fiche, UploadedFile $file, DocumentUsage $category, string $actor): DocumentExtraction
    {
        // Une seule lecture à la fois par fiche : le document suivant attend la fin de la précédente.
        if (null !== $this->extractions->enCours($fiche)) {
            throw new \DomainException('Une extraction est déjà en cours pour cette fiche : attendez qu’elle se termine avant de déposer un autre document.');
        }
        if (!$this->categories->allows($fiche->type(), $category)) {
            throw new \DomainException('Cette catégorie documentaire n’est pas autorisée pour ce type de fiche.');
        }
        $this->assertBelowMaximumCount($fiche, $category);
        $path = $file->getRealPath();
        if (false === $path) {
            throw new \DomainException('Le PDF téléversé est introuvable.');
        }
        $this->pdf->inspect($path);
        // Le plafond de taille est celui de la politique documentaire (maximumBytes de l'usage), comme pour les dépôts hors OCR.
        $asset = $this->uploader->upload($file, $fiche, $category);
        try {
            $extraction = new DocumentExtraction($fiche, $asset, $category->value, $this->catalog->snapshot($fiche->type()), $actor);
            $resource = new RessourceLieu();
            $resource->changeDamAssetId($asset->id());
            $resource->configureDocument($category);
            $resource->changeLegende($asset->originalFilename());
            $resource->changeSource('box_ocr');
            $resource->changePosition($fiche->resources()->count());
            $fiche->addResource($resource);
            $this->entityManager->persist($asset);
            $this->entityManager->persist($resource);
            $this->entityManager->persist($extraction);
            $this->outbox->enqueue(new ExtractDocument($extraction->id()));
            $this->entityManager->flush();

            return $extraction;
        } catch (\Throwable $error) {
            try {
                $this->uploader->delete($asset);
            } catch (\Throwable) {
            }
            throw $error;
        }
    }

    public function retry(DocumentExtraction $failed, string $actor): DocumentExtraction
    {
        if (\App\Ocr\Enum\ExtractionStatus::Failed !== $failed->status()) {
            throw new \DomainException('Seule une extraction en échec peut être relancée.');
        }
        if (null !== $this->extractions->enCours($failed->fiche())) {
            throw new \DomainException('Une extraction est déjà en cours pour cette fiche : attendez qu’elle se termine avant de relancer.');
        }
        $next = new DocumentExtraction($failed->fiche(), $failed->document(), $failed->documentCategory(), $this->catalog->snapshot($failed->fiche()->type()), $actor, $failed);
        $this->entityManager->persist($next);
        $this->outbox->enqueue(new ExtractDocument($next->id()));
        $this->entityManager->flush();

        return $next;
    }

    // Même garde que les processors documentaires : le nombre maximal de documents par usage vaut aussi pour le dépôt OCR.
    private function assertBelowMaximumCount(Fiche $fiche, DocumentUsage $usage): void
    {
        $maximum = $usage->maximumCount();
        if (null === $maximum) {
            return;
        }
        $count = 0;
        foreach ($fiche->resources() as $resource) {
            // Le dépôt OCR ne rattache pas de salle : pour un usage par salle, seuls les documents sans salle comptent.
            if (NatureRessource::Document === $resource->nature() && $resource->usage() === $usage->value && (!$usage->requiresRoom() || (null === $resource->salle() && null === $resource->restaurantSalle()))) {
                ++$count;
            }
        }
        if ($count >= $maximum) {
            throw new \DomainException('Le nombre maximal de documents pour cet usage est atteint.');
        }
    }
}
