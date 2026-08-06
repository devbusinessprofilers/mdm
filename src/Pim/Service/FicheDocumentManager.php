<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Dam\Enum\DocumentUsage;
use App\Dam\Message\DeleteMedia;
use App\Dam\Message\PublishDocument;
use App\Dam\Message\UnpublishDocument;
use App\Dam\Service\FicheDocumentUploader;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Message\IndexFiche;
use App\Shared\Outbox\OutboxPublisherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class FicheDocumentManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OutboxPublisherInterface $outbox,
        private FicheDocumentUploader $uploader,
        private InternalFicheMutationPolicy $mutationPolicy,
    ) {}

    /** @param array<string, mixed> $data */
    public function updateMetadata(RessourceLieu $document, Fiche $fiche, array $data, string $actor): void
    {
        $this->mutationPolicy->execute($fiche, function () use ($document, $fiche, $data, $actor): void {
            $this->updateMetadataWithinMutation($document, $fiche, $data, $actor);
        });
    }

    /** @param array<string, mixed> $data */
    private function updateMetadataWithinMutation(RessourceLieu $document, Fiche $fiche, array $data, string $actor): void
    {
        $document->changeLegende(is_string($data['title'] ?? null) ? $data['title'] : null);
        $wasPublished = 'published' === $document->publicationStatus()?->value;
        $document->changeSource(is_string($data['source'] ?? null) ? $data['source'] : null);
        $document->changeKeywords(is_string($data['keywords'] ?? null) ? $data['keywords'] : null);
        $document->changeRightsExpiresAt(($data['rightsExpiresAt'] ?? null) instanceof \DateTimeImmutable ? $data['rightsExpiresAt'] : null);
        if ($wasPublished && !$document->rightsGranted()) {
            $this->unpublish($document);
        }
        $this->changed($fiche);
    }

    public function replace(RessourceLieu $document, Fiche $fiche, UploadedFile $file, DocumentUsage $usage): void
    {
        $this->mutationPolicy->execute($fiche, function () use ($document, $fiche, $file, $usage): void {
            $this->replaceWithinMutation($document, $fiche, $file, $usage);
        });
    }

    private function replaceWithinMutation(RessourceLieu $document, Fiche $fiche, UploadedFile $file, DocumentUsage $usage): void
    {
        $asset = $this->uploader->upload($file, $fiche, $usage);
        $old = $document->damAssetId();
        try {
            $this->unpublish($document);
            $this->entityManager->persist($asset);
            $document->changeDamAssetId($asset->id());
            $this->outbox->enqueue(new DeleteMedia($old));
            $this->changed($fiche);
        } catch (\Throwable $exception) {
            try { $this->uploader->delete($asset); } catch (\Throwable) {}
            throw $exception;
        }
    }

    public function togglePublication(RessourceLieu $document, Fiche $fiche): void
    {
        $this->mutationPolicy->execute($fiche, function () use ($document, $fiche): void {
            $this->togglePublicationWithinMutation($document, $fiche);
        });
    }

    private function togglePublicationWithinMutation(RessourceLieu $document, Fiche $fiche): void
    {
        if ('published' === $document->publicationStatus()?->value) {
            $this->unpublish($document);
        } else {
            $document->requestPublication();
            $this->outbox->enqueue(new PublishDocument($document->id()));
        }
        $this->changed($fiche);
    }

    public function delete(RessourceLieu $document, Fiche $fiche): void
    {
        $this->mutationPolicy->execute($fiche, function () use ($document, $fiche): void {
            $this->deleteWithinMutation($document, $fiche);
        });
    }

    private function deleteWithinMutation(RessourceLieu $document, Fiche $fiche): void
    {
        $this->unpublish($document);
        $this->outbox->enqueue(new DeleteMedia($document->damAssetId()));
        $fiche->removeResource($document);
        $this->entityManager->remove($document);
        $this->changed($fiche);
    }

    private function unpublish(RessourceLieu $document): void
    {
        $key = $document->requestUnpublication();
        if (null !== $key) { $this->outbox->enqueue(new UnpublishDocument($document->id(), $key)); }
    }

    private function changed(Fiche $fiche): void
    {
        $fiche->markChanged();
        $this->outbox->enqueue(new IndexFiche($fiche->idString()));
        $this->entityManager->flush();
    }
}
