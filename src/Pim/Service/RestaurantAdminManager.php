<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Dam\Entity\MediaAsset;
use App\Dam\Enum\DocumentUsage;
use App\Dam\Message\DeleteMedia;
use App\Dam\Service\FicheDocumentUploader;
use App\Dam\Service\FicheImageUploader;
use App\Dam\Service\ImageVariantRegistry;
use App\Enrichment\Service\FicheTranslationScheduler;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Enum\NatureRessource;
use App\Pim\Message\IndexFiche;
use App\Shared\Message\MediaUploaded;
use App\Shared\Outbox\OutboxPublisherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class RestaurantAdminManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OutboxPublisherInterface $outbox,
        private FicheImageUploader $imageUploader,
        private FicheDocumentUploader $documentUploader,
        private FicheTranslationScheduler $translationScheduler,
    ) {
    }

    /** @return list<string> */
    public function photoAssetIds(Restaurant $restaurant): array
    {
        return array_map(static fn (RessourceLieu $resource): string => $resource->damAssetId(), array_values(array_filter(
            $restaurant->ressources()->toArray(), static fn (RessourceLieu $resource): bool => NatureRessource::Photo === $resource->nature(),
        )));
    }

    /** @param FormInterface<mixed> $form
     * @param list<string> $existingMediaIds
     */
    public function save(Restaurant $restaurant, FormInterface $form, array $existingMediaIds, string $actor): void
    {
        $images = [];
        $documents = [];
        try {
            foreach ($form->get('ressources') as $resourceForm) {
                $file = $resourceForm->get('image')->getData();
                $resource = $resourceForm->getData();
                if (!$file instanceof UploadedFile || !$resource instanceof RessourceLieu) {
                    continue;
                }
                $media = $this->imageUploader->upload($file, $restaurant->fiche());
                $images[] = $media;
                $this->entityManager->persist($media);
                $resource->changeDamAssetId($media->id());
                $resource->changeNature(NatureRessource::Photo);
                $this->outbox->enqueue(new MediaUploaded($media->id(), $media->originalStorageKey(), $media->checksum(), ImageVariantRegistry::names()));
            }
            $this->uploadDocuments($form, 'menus', DocumentUsage::RestaurantMenu, $restaurant, $documents, $actor);
            $this->uploadDocuments($form, 'supportsCommerciaux', DocumentUsage::CommercialSupport, $restaurant, $documents, $actor);
            foreach (array_diff($existingMediaIds, $this->photoAssetIds($restaurant)) as $removed) {
                if ('' !== $removed) {
                    $this->outbox->enqueue(new DeleteMedia($removed));
                }
            }
            $this->entityManager->persist($restaurant);
            $this->translationScheduler->schedule($restaurant->fiche());
            $this->outbox->enqueue(new IndexFiche($restaurant->fiche()->idString()));
            // Liaison Lieu modifiée : le payload des fiches détachée/attachée
            // change aussi, sans transition de workflow (flush sous
            // suppression pour ces fiches).
            $liees = $restaurant->drainFichesLieesAResynchroniser();
            foreach ($liees as $ficheLiee) {
                $this->outbox->enqueue(new IndexFiche($ficheLiee->idString()));
            }
            Fiche::preserveWorkflowsDuring($liees, fn () => $this->entityManager->flush());
        } catch (\Throwable $exception) {
            $this->cleanup($images, $documents);
            throw $exception;
        }
    }

    /** @param FormInterface<mixed> $form
     * @param list<MediaAsset> $uploadedDocuments
     */
    private function uploadDocuments(FormInterface $form, string $field, DocumentUsage $usage, Restaurant $restaurant, array &$uploadedDocuments, string $actor): void
    {
        $files = $form->get($field)->getData();
        foreach (is_array($files) ? $files : [] as $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }
            $asset = $this->documentUploader->upload($file, $restaurant->fiche(), $usage);
            $uploadedDocuments[] = $asset;
            $this->entityManager->persist($asset);
            $resource = new RessourceLieu();
            $resource->configureDocument($usage);
            $resource->changeDamAssetId($asset->id());
            $title = $form->get('documentTitle')->getData();
            $resource->changeLegende(is_string($title) && '' !== trim($title) ? $title : pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
            $source = $form->get('documentSource')->getData();
            $resource->changeSource(is_string($source) ? $source : null);
            $restaurant->addRessource($resource);
        }
    }

    /** @param list<MediaAsset> $images
     * @param list<MediaAsset> $documents
     */
    private function cleanup(array $images, array $documents): void
    {
        foreach ($images as $asset) {
            try {
                $this->imageUploader->delete($asset);
            } catch (\Throwable) {
            }
        }
        foreach ($documents as $asset) {
            try {
                $this->documentUploader->delete($asset);
            } catch (\Throwable) {
            }
        }
    }
}
