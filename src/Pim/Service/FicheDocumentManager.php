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
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Restaurant\RestaurantSalle;
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

    /**
     * Dépôt documentaire du Restaurant — pendant de LieuDocumentManager::upload,
     * la salle rattachée étant une RestaurantSalle (l'entité porte les gardes
     * d'appartenance dans addRessource).
     *
     * @param list<UploadedFile> $files
     * @param array{usage: DocumentUsage, salle: RestaurantSalle|null, title: string|null, source: string|null} $data
     */
    public function upload(Restaurant $restaurant, array $files, array $data, string $actor): int
    {
        return $this->mutationPolicy->execute(
            $restaurant->fiche(),
            fn (): int => $this->uploadWithinMutation($restaurant, $files, $data, $actor),
        );
    }

    /**
     * @param list<UploadedFile> $files
     * @param array{usage: DocumentUsage, salle: RestaurantSalle|null, title: string|null, source: string|null} $data
     */
    private function uploadWithinMutation(Restaurant $restaurant, array $files, array $data, string $actor): int
    {
        $usage = $data['usage'];
        $salle = $data['salle'];
        if ($usage->requiresRoom() && null === $salle) { throw new \DomainException('Un plan de salle doit être rattaché à une salle.'); }
        if (!$this->withinMaximum($restaurant->fiche(), $usage, $salle, count($files))) { throw new \DomainException('Le nombre maximal de documents pour cet usage serait dépassé.'); }
        $uploaded = [];
        try {
            foreach ($files as $file) {
                $asset = $this->uploader->upload($file, $restaurant->fiche(), $usage);
                $uploaded[] = $asset;
                $document = new RessourceLieu();
                $document->configureDocument($usage);
                $document->changeDamAssetId($asset->id());
                $document->changeRestaurantSalle($salle);
                $document->changeLegende($data['title']);
                $document->changeSource($data['source']);
                $restaurant->addRessource($document);
                $this->entityManager->persist($asset);
            }
            $this->changed($restaurant->fiche());
        } catch (\Throwable $exception) {
            foreach ($uploaded as $asset) { try { $this->uploader->delete($asset); } catch (\Throwable) {} }
            throw $exception;
        }

        return count($uploaded);
    }

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
        // Le formulaire Restaurant expose la salle rattachée : appliquée
        // seulement quand la clé est présente, les autres gammes ne l'ont pas.
        if (\array_key_exists('salle', $data)) {
            $salle = ($data['salle'] ?? null) instanceof RestaurantSalle ? $data['salle'] : null;
            $usage = $document->documentUsage();
            if (null !== $usage && $usage->requiresRoom() && null === $salle) { throw new \DomainException('Un plan de salle doit être rattaché à une salle.'); }
            if (null !== $usage && $salle !== $document->restaurantSalle() && !$this->withinMaximum($fiche, $usage, $salle, 1, $document)) { throw new \DomainException('Le nombre maximal de documents pour cet usage serait dépassé.'); }
            $document->changeRestaurantSalle($salle);
        }
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

    private function withinMaximum(Fiche $fiche, DocumentUsage $usage, ?RestaurantSalle $salle, int $added, ?RessourceLieu $current = null): bool
    {
        if (null === $usage->maximumCount()) { return true; }
        $count = 0;
        foreach ($fiche->resources() as $resource) {
            if ($resource !== $current && $resource->usage() === $usage->value && (!$usage->requiresRoom() || $resource->restaurantSalle() === $salle)) { ++$count; }
        }

        return $count + $added <= $usage->maximumCount();
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
