<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Dam\Entity\MediaAsset;
use App\Dam\Message\DeleteMedia;
use App\Dam\Message\UnpublishDocument;
use App\Dam\Service\ImageVariantRegistry;
use App\Dam\Service\LieuImageUploader;
use App\Enrichment\Service\FicheTranslationScheduler;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Enum\NatureRessource;
use App\Pim\Message\IndexFiche;
use App\Shared\Message\MediaUploaded;
use App\Shared\Outbox\OutboxPublisherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class LieuAdminManager
{
    public function __construct(
        private readonly DocumentsAdministratifsDepot $depotAdministratif,
        private EntityManagerInterface $entityManager,
        private OutboxPublisherInterface $outbox,
        private LieuImageUploader $imageUploader,
        private FicheTranslationScheduler $translationScheduler,
    ) {}

    /** @return list<string> */
    public function photoAssetIds(Lieu $lieu): array
    {
        return array_values(array_map(static fn (RessourceLieu $resource): string => $resource->damAssetId(), array_filter(
            $lieu->ressources()->toArray(), static fn (RessourceLieu $resource): bool => NatureRessource::Photo === $resource->nature(),
        )));
    }

    /** @param FormInterface<mixed> $form
     *  @param list<string> $existingMediaIds
     */
    public function save(Lieu $lieu, FormInterface $form, array $existingMediaIds): void
    {
        $uploaded = [];
        $documents = [];
        try {
            foreach ($form->get('ressources') as $resourceForm) {
                $file = $resourceForm->get('image')->getData();
                $resource = $resourceForm->getData();
                if (!$file instanceof UploadedFile || !$resource instanceof RessourceLieu) { continue; }
                $media = $this->imageUploader->upload($file, $lieu);
                $uploaded[] = $media;
                $this->entityManager->persist($media);
                $resource->changeDamAssetId($media->id());
                $resource->changeNature(NatureRessource::Photo);
                $this->outbox->enqueue(new MediaUploaded($media->id(), $media->originalStorageKey(), $media->checksum(), ImageVariantRegistry::names()));
            }
            $this->depotAdministratif->deposer($form, $lieu, $documents);
            foreach (array_diff($existingMediaIds, $this->photoAssetIds($lieu)) as $removed) {
                if ('' !== $removed) { $this->outbox->enqueue(new DeleteMedia($removed)); }
            }
            $this->entityManager->persist($lieu);
            $this->translationScheduler->schedule($lieu->fiche());
            $this->outbox->enqueue(new IndexFiche($lieu->fiche()->idString()));
            // Liaison Restaurant modifiée : le payload des fiches détachée/
            // attachée change aussi, sans transition de workflow (le flush
            // sous suppression évite le markChanged du PreUpdate détail).
            $liees = $lieu->drainFichesLieesAResynchroniser();
            foreach ($liees as $ficheLiee) {
                $this->outbox->enqueue(new IndexFiche($ficheLiee->idString()));
            }
            Fiche::preserveWorkflowsDuring($liees, fn () => $this->entityManager->flush());
        } catch (\Throwable $exception) {
            $this->cleanup($uploaded);
            $this->depotAdministratif->nettoyer($documents);
            throw $exception;
        }
    }

    public function delete(Lieu $lieu): void
    {
        foreach ($lieu->ressources() as $resource) {
            if ('' !== $resource->damAssetId()) { $this->outbox->enqueue(new DeleteMedia($resource->damAssetId())); }
            if (null !== $resource->publicStorageKey()) { $this->outbox->enqueue(new UnpublishDocument($resource->id(), $resource->publicStorageKey())); }
        }
        // Le restaurant lié survit (FK SET NULL) mais son payload change.
        $restaurant = $lieu->restaurant();
        if (null !== $restaurant) {
            $restaurant->syncLieu(null);
            $this->outbox->enqueue(new IndexFiche($restaurant->fiche()->idString()));
        }
        $this->entityManager->remove($lieu);
        Fiche::preserveWorkflowsDuring(
            null === $restaurant ? [] : [$restaurant->fiche()],
            fn () => $this->entityManager->flush(),
        );
    }

    public function archive(Lieu $lieu, string $actor): void
    {
        foreach ($lieu->ressources() as $resource) {
            if (NatureRessource::Document !== $resource->nature()) { continue; }
            $key = $resource->archiveDocument();
            if (null !== $key) { $this->outbox->enqueue(new UnpublishDocument($resource->id(), $key)); }
        }
        $lieu->fiche()->archive($actor);
        $this->outbox->enqueue(new IndexFiche($lieu->fiche()->idString()));
        $this->entityManager->flush();
    }

    /** @param list<MediaAsset> $assets */
    private function cleanup(array $assets): void
    {
        foreach ($assets as $asset) { try { $this->imageUploader->delete($asset); } catch (\Throwable) {} }
    }
}
