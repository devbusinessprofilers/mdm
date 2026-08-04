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
use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Enum\NatureRessource;
use App\Pim\Message\IndexFiche;
use App\Shared\Message\MediaUploaded;
use App\Shared\Outbox\OutboxPublisherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class ActiviteAdminManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OutboxPublisherInterface $outbox,
        private FicheImageUploader $imageUploader,
        private FicheDocumentUploader $documentUploader,
        private FicheTranslationScheduler $translationScheduler,
    ) {}

    /** @return list<string> */
    public function photoAssetIds(Activite $activite): array
    {
        return array_values(array_map(
            static fn (RessourceLieu $resource): string => $resource->damAssetId(),
            array_filter($activite->ressources()->toArray(), static fn (RessourceLieu $resource): bool => NatureRessource::Photo === $resource->nature()),
        ));
    }

    /** @param FormInterface<mixed> $form
     *  @param list<string> $existingMediaIds
     */
    public function save(Activite $activite, FormInterface $form, array $existingMediaIds, string $actor): void
    {
        $uploaded = [];
        $uploadedDocuments = [];
        try {
            foreach ($form->get('ressources') as $resourceForm) {
                $file = $resourceForm->get('image')->getData();
                $resource = $resourceForm->getData();
                if (!$file instanceof UploadedFile || !$resource instanceof RessourceLieu) {
                    continue;
                }
                $media = $this->imageUploader->upload($file, $activite->fiche());
                $uploaded[] = $media;
                $this->entityManager->persist($media);
                $resource->changeDamAssetId($media->id());
                $resource->changeNature(NatureRessource::Photo);
                $this->outbox->enqueue(new MediaUploaded($media->id(), $media->originalStorageKey(), $media->checksum(), ImageVariantRegistry::names()));
            }
            $documents = $form->get('supportsCommerciaux')->getData();
            foreach (is_array($documents) ? $documents : [] as $file) {
                if (!$file instanceof UploadedFile) {
                    continue;
                }
                $asset = $this->documentUploader->upload($file, $activite->fiche(), DocumentUsage::CommercialSupport);
                $uploadedDocuments[] = $asset;
                $this->entityManager->persist($asset);
                $resource = new RessourceLieu();
                $resource->configureDocument(DocumentUsage::CommercialSupport);
                $resource->changeDamAssetId($asset->id());
                $title = $form->get('supportTitle')->getData();
                $resource->changeLegende(is_string($title) && '' !== trim($title) ? $title : pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
                $source = $form->get('supportSource')->getData();
                $resource->changeSource(is_string($source) ? $source : null);
                if (true === $form->get('supportRightsGranted')->getData()) {
                    $resource->grantRights($actor);
                }
                $activite->addRessource($resource);
            }
            foreach (array_diff($existingMediaIds, $this->photoAssetIds($activite)) as $removed) {
                if ('' !== $removed) {
                    $this->outbox->enqueue(new DeleteMedia($removed));
                }
            }
            $this->entityManager->persist($activite);
            $this->translationScheduler->schedule($activite->fiche());
            $this->outbox->enqueue(new IndexFiche($activite->fiche()->idString()));
            $this->entityManager->flush();
        } catch (\Throwable $exception) {
            $this->cleanupImages($uploaded);
            $this->cleanupDocuments($uploadedDocuments);
            throw $exception;
        }
    }

    /** @param list<MediaAsset> $assets */
    private function cleanupImages(array $assets): void
    {
        foreach ($assets as $asset) {
            try { $this->imageUploader->delete($asset); } catch (\Throwable) {}
        }
    }

    /** @param list<MediaAsset> $assets */
    private function cleanupDocuments(array $assets): void
    {
        foreach ($assets as $asset) {
            try { $this->documentUploader->delete($asset); } catch (\Throwable) {}
        }
    }
}
