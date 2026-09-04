<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Dam\Enum\DocumentUsage;
use App\Dam\Message\DeleteMedia;
use App\Dam\Message\PublishDocument;
use App\Dam\Message\UnpublishDocument;
use App\Dam\Service\FicheDocumentUploader;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Entity\Lieu\Salle;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Restaurant\RestaurantSalle;
use App\Pim\Message\IndexFiche;
use App\Shared\Outbox\OutboxPublisherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Documents d'une fiche, toutes gammes : dépôt (Lieu et Restaurant, avec
 * leurs salles), métadonnées, remplacement du fichier, publication et
 * suppression. Chaque geste s'exécute sous la politique de mutation interne
 * et réindexe la fiche.
 */
final readonly class FicheDocumentManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OutboxPublisherInterface $outbox,
        private FicheDocumentUploader $uploader,
        private InternalFicheMutationPolicy $mutationPolicy,
    ) {
    }

    /**
     * @param list<UploadedFile>                                                                                      $files
     * @param array{usage: DocumentUsage, salle: Salle|RestaurantSalle|null, title: string|null, source: string|null} $data
     */
    public function upload(Lieu|Restaurant $entite, array $files, array $data): int
    {
        return $this->mutationPolicy->execute(
            $entite->fiche(),
            fn (): int => $this->uploadWithinMutation($entite, $files, $data),
        );
    }

    /**
     * @param list<UploadedFile>                                                                                      $files
     * @param array{usage: DocumentUsage, salle: Salle|RestaurantSalle|null, title: string|null, source: string|null} $data
     */
    private function uploadWithinMutation(Lieu|Restaurant $entite, array $files, array $data): int
    {
        $usage = $data['usage'];
        $salle = $data['salle'];
        $this->assertSalle($entite->fiche(), $usage, $salle, count($files));
        $uploaded = [];
        try {
            foreach ($files as $file) {
                $asset = $this->uploader->upload($file, $entite->fiche(), $usage);
                $uploaded[] = $asset;
                $document = new RessourceLieu();
                $document->configureDocument($usage);
                $document->changeDamAssetId($asset->id());
                $document->rattacherSalle($salle);
                $document->changeLegende($data['title']);
                $document->changeSource($data['source']);
                // L'entité vérifie l'appartenance de la salle.
                $entite->addRessource($document);
                $this->entityManager->persist($asset);
            }
            $this->changed($entite->fiche());
        } catch (\Throwable $exception) {
            foreach ($uploaded as $asset) {
                try {
                    $this->uploader->delete($asset);
                } catch (\Throwable) {
                }
            }
            throw $exception;
        }

        return count($uploaded);
    }

    /**
     * Métadonnées : titre, source, mots-clés, échéance des droits ; la salle
     * rattachée quand la clé est présente (Lieu, Restaurant) et l'usage quand
     * il l'est (Lieu). Un document publié qui perd ses droits est dépublié.
     *
     * @param array<string, mixed> $data
     */
    public function updateMetadata(RessourceLieu $document, Fiche $fiche, array $data): void
    {
        $this->mutationPolicy->execute($fiche, function () use ($document, $fiche, $data): void {
            $this->updateMetadataWithinMutation($document, $fiche, $data);
        });
    }

    /** @param array<string, mixed> $data */
    private function updateMetadataWithinMutation(RessourceLieu $document, Fiche $fiche, array $data): void
    {
        $usage = ($data['usage'] ?? null) instanceof DocumentUsage ? $data['usage'] : $document->documentUsage();
        if (\array_key_exists('salle', $data) || \array_key_exists('usage', $data)) {
            $salle = \array_key_exists('salle', $data) ? $data['salle'] : $document->salleRattachee();
            $salle = $salle instanceof Salle || $salle instanceof RestaurantSalle ? $salle : null;
            if (null !== $usage && ($usage !== $document->documentUsage() || $salle !== $document->salleRattachee())) {
                $this->assertSalle($fiche, $usage, $salle, 1, $document);
            }
            if (null !== $usage && $usage !== $document->documentUsage()) {
                $this->unpublish($document);
                $document->configureDocument($usage);
            }
            $document->rattacherSalle($salle);
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

    /** Remplace le fichier en conservant l'usage du document ; la copie publique est retirée. */
    public function replace(RessourceLieu $document, Fiche $fiche, UploadedFile $file): void
    {
        $this->mutationPolicy->execute($fiche, function () use ($document, $fiche, $file): void {
            $this->replaceWithinMutation($document, $fiche, $file);
        });
    }

    private function replaceWithinMutation(RessourceLieu $document, Fiche $fiche, UploadedFile $file): void
    {
        $usage = $document->documentUsage() ?? throw new \DomainException('Usage documentaire invalide.');
        $asset = $this->uploader->upload($file, $fiche, $usage);
        $old = $document->damAssetId();
        try {
            $this->unpublish($document);
            $this->entityManager->persist($asset);
            $document->changeDamAssetId($asset->id());
            $this->outbox->enqueue(new DeleteMedia($old));
            $this->changed($fiche);
        } catch (\Throwable $exception) {
            try {
                $this->uploader->delete($asset);
            } catch (\Throwable) {
            }
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
        // Le Lieu tient sa propre collection miroir des ressources.
        $document->lieu()?->removeRessource($document);
        $fiche->removeResource($document);
        $this->entityManager->remove($document);
        $this->changed($fiche);
    }

    /** Un plan de salle exige une salle ; le plafond par usage (et par salle) ne doit pas être dépassé. */
    private function assertSalle(Fiche $fiche, DocumentUsage $usage, Salle|RestaurantSalle|null $salle, int $added, ?RessourceLieu $current = null): void
    {
        if ($usage->requiresRoom() && null === $salle) {
            throw new \DomainException('Un plan de salle doit être rattaché à une salle.');
        }
        if (!$this->withinMaximum($fiche, $usage, $salle, $added, $current)) {
            throw new \DomainException('Le nombre maximal de documents pour cet usage serait dépassé.');
        }
    }

    private function withinMaximum(Fiche $fiche, DocumentUsage $usage, Salle|RestaurantSalle|null $salle, int $added, ?RessourceLieu $current): bool
    {
        if (null === $usage->maximumCount()) {
            return true;
        }
        $count = 0;
        foreach ($fiche->resources() as $resource) {
            if ($resource !== $current && $resource->usage() === $usage->value && (!$usage->requiresRoom() || $resource->salleRattachee() === $salle)) {
                ++$count;
            }
        }

        return $count + $added <= $usage->maximumCount();
    }

    private function unpublish(RessourceLieu $document): void
    {
        $key = $document->requestUnpublication();
        if (null !== $key) {
            $this->outbox->enqueue(new UnpublishDocument($document->id(), $key));
        }
    }

    private function changed(Fiche $fiche): void
    {
        $fiche->markChanged();
        $this->outbox->enqueue(new IndexFiche($fiche->idString()));
        $this->entityManager->flush();
    }
}
