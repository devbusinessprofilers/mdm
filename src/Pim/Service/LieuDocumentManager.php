<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Dam\Enum\DocumentUsage;
use App\Dam\Message\DeleteMedia;
use App\Dam\Message\PublishDocument;
use App\Dam\Message\UnpublishDocument;
use App\Dam\Service\LieuDocumentUploader;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Entity\Lieu\Salle;
use App\Pim\Message\IndexFiche;
use App\Shared\Outbox\OutboxPublisherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class LieuDocumentManager
{
    public function __construct(
        private LieuDocumentUploader $uploader,
        private EntityManagerInterface $entityManager,
        private OutboxPublisherInterface $outbox,
        private InternalFicheMutationPolicy $mutationPolicy,
    ) {}

    /** @param list<UploadedFile> $files
     *  @param array{usage: DocumentUsage, salle: Salle|null, title: string|null, source: string|null} $data
     */
    public function upload(Lieu $lieu, array $files, array $data, string $actor): int
    {
        return $this->mutationPolicy->execute(
            $lieu->fiche(),
            fn (): int => $this->uploadWithinMutation($lieu, $files, $data, $actor),
        );
    }

    /** @param list<UploadedFile> $files
     *  @param array{usage: DocumentUsage, salle: Salle|null, title: string|null, source: string|null} $data
     */
    private function uploadWithinMutation(Lieu $lieu, array $files, array $data, string $actor): int
    {
        $usage = $data['usage'];
        $salle = $data['salle'];
        if ($usage->requiresRoom() && null === $salle) { throw new \DomainException('Un plan de salle doit être rattaché à une salle.'); }
        if (!$this->withinMaximum($lieu, $usage, $salle, count($files))) { throw new \DomainException('Le nombre maximal de documents pour cet usage serait dépassé.'); }
        $uploaded = [];
        try {
            foreach ($files as $file) {
                $asset = $this->uploader->upload($file, $lieu, $usage);
                $uploaded[] = $asset;
                $document = new RessourceLieu();
                $document->configureDocument($usage);
                $document->changeDamAssetId($asset->id());
                $document->changeSalle($salle);
                $document->changeLegende($data['title']);
                $document->changeSource($data['source']);
                $lieu->addRessource($document);
                $this->entityManager->persist($asset);
            }
            $this->changed($lieu);
        } catch (\Throwable $exception) {
            foreach ($uploaded as $asset) { try { $this->uploader->delete($asset); } catch (\Throwable) {} }
            throw $exception;
        }

        return count($uploaded);
    }

    /** @param array{usage: DocumentUsage, salle: Salle|null, title: string|null, source: string|null, keywords?: string|null, rightsExpiresAt?: \DateTimeImmutable|null} $data */
    public function update(RessourceLieu $document, Lieu $lieu, array $data, string $actor): void
    {
        $this->mutationPolicy->execute($lieu->fiche(), function () use ($document, $lieu, $data, $actor): void {
            $this->updateWithinMutation($document, $lieu, $data, $actor);
        });
    }

    /** @param array{usage: DocumentUsage, salle: Salle|null, title: string|null, source: string|null, keywords?: string|null, rightsExpiresAt?: \DateTimeImmutable|null} $data */
    private function updateWithinMutation(RessourceLieu $document, Lieu $lieu, array $data, string $actor): void
    {
        $usage = $data['usage'];
        $salle = $data['salle'];
        if ($usage->requiresRoom() && null === $salle) { throw new \DomainException('Un plan de salle doit être rattaché à une salle.'); }
        if (!$this->withinMaximum($lieu, $usage, $salle, 1, $document)) { throw new \DomainException('Le nombre maximal de documents pour cet usage serait dépassé.'); }
        if ($usage !== $document->documentUsage()) { $this->unpublish($document); }
        $document->configureDocument($usage);
        $document->changeSalle($salle);
        $document->changeLegende($data['title']);
        $wasPublished = 'published' === $document->publicationStatus()?->value;
        $document->changeSource($data['source']);
        $document->changeKeywords(is_string($data['keywords'] ?? null) ? $data['keywords'] : null);
        $document->changeRightsExpiresAt(($data['rightsExpiresAt'] ?? null) instanceof \DateTimeImmutable ? $data['rightsExpiresAt'] : null);
        if ($wasPublished && !$document->rightsGranted()) { $this->unpublish($document); }
        $this->changed($lieu);
    }

    public function replace(RessourceLieu $document, Lieu $lieu, UploadedFile $file): void
    {
        $this->mutationPolicy->execute($lieu->fiche(), function () use ($document, $lieu, $file): void {
            $this->replaceWithinMutation($document, $lieu, $file);
        });
    }

    private function replaceWithinMutation(RessourceLieu $document, Lieu $lieu, UploadedFile $file): void
    {
        $usage = $document->documentUsage() ?? throw new \DomainException('Usage invalide.');
        $asset = $this->uploader->upload($file, $lieu, $usage);
        $old = $document->damAssetId();
        try {
            $this->unpublish($document);
            $this->entityManager->persist($asset);
            $document->changeDamAssetId($asset->id());
            $this->outbox->enqueue(new DeleteMedia($old));
            $this->changed($lieu);
        } catch (\Throwable $exception) {
            try { $this->uploader->delete($asset); } catch (\Throwable) {}
            throw $exception;
        }
    }

    public function togglePublication(RessourceLieu $document, Lieu $lieu): void
    {
        $this->mutationPolicy->execute($lieu->fiche(), function () use ($document, $lieu): void {
            $this->togglePublicationWithinMutation($document, $lieu);
        });
    }

    private function togglePublicationWithinMutation(RessourceLieu $document, Lieu $lieu): void
    {
        if ('published' !== $document->publicationStatus()?->value) { $document->requestPublication(); $this->outbox->enqueue(new PublishDocument($document->id())); }
        else { $this->unpublish($document); }
        $this->changed($lieu);
    }

    public function delete(RessourceLieu $document, Lieu $lieu): void
    {
        $this->mutationPolicy->execute($lieu->fiche(), function () use ($document, $lieu): void {
            $this->deleteWithinMutation($document, $lieu);
        });
    }

    private function deleteWithinMutation(RessourceLieu $document, Lieu $lieu): void
    {
        $this->unpublish($document);
        $this->outbox->enqueue(new DeleteMedia($document->damAssetId()));
        $lieu->removeRessource($document);
        $this->entityManager->remove($document);
        $this->changed($lieu);
    }

    private function withinMaximum(Lieu $lieu, DocumentUsage $usage, ?Salle $salle, int $added, ?RessourceLieu $current = null): bool
    {
        if (null === $usage->maximumCount()) { return true; }
        $count = 0;
        foreach ($lieu->ressources() as $resource) {
            if ($resource !== $current && $resource->usage() === $usage->value && (!$usage->requiresRoom() || $resource->salle() === $salle)) { ++$count; }
        }

        return $count + $added <= $usage->maximumCount();
    }

    private function unpublish(RessourceLieu $document): void
    {
        $key = $document->requestUnpublication();
        if (null !== $key) { $this->outbox->enqueue(new UnpublishDocument($document->id(), $key)); }
    }

    private function changed(Lieu $lieu): void
    {
        $lieu->fiche()->markChanged();
        $this->outbox->enqueue(new IndexFiche($lieu->id()));
        $this->entityManager->flush();
    }
}
