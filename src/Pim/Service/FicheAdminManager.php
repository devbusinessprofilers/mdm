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
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Pim\Enum\NatureRessource;
use App\Pim\Enum\TypeFiche;
use App\Pim\Message\IndexFiche;
use App\Shared\Message\MediaUploaded;
use App\Shared\Outbox\OutboxPublisherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Enregistrement du formulaire principal d'une fiche, toutes gammes : photos
 * de la collection `ressources`, documents déposés par les champs de fichiers
 * de la gamme (menus, supports commerciaux) avec le titre et la source saisis
 * à côté, pièces administratives (DocumentsAdministratifsDepot), médias
 * retirés, traductions replanifiées, réindexation.
 */
final readonly class FicheAdminManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OutboxPublisherInterface $outbox,
        private FicheImageUploader $imageUploader,
        private FicheDocumentUploader $documentUploader,
        private FicheTranslationScheduler $translationScheduler,
        private FicheMutation $mutations,
        private DocumentsAdministratifsDepot $depotAdministratif,
    ) {
    }

    /**
     * Champs de dépôt documentaire du formulaire d'une gamme : champ de
     * fichiers → usage, et les champs titre/source appliqués aux fichiers.
     *
     * @return array{champs: array<string, DocumentUsage>, titre: string, source: string}|null
     */
    public static function depotsDocumentaires(TypeFiche $type): ?array
    {
        return match ($type) {
            TypeFiche::Restaurant => [
                'champs' => ['menus' => DocumentUsage::RestaurantMenu, 'supportsCommerciaux' => DocumentUsage::CommercialSupport],
                'titre' => 'documentTitle', 'source' => 'documentSource',
            ],
            TypeFiche::Activite, TypeFiche::ServiceEvenementiel => [
                'champs' => ['supportsCommerciaux' => DocumentUsage::CommercialSupport],
                'titre' => 'supportTitle', 'source' => 'supportSource',
            ],
            // Le Lieu dépose ses documents par le bloc Médias.
            TypeFiche::Lieu, TypeFiche::Traiteur => null,
        };
    }

    /** @return list<string> */
    public function photoAssetIds(Lieu|Restaurant|Activite|ServiceEvenementiel $entite): array
    {
        $ids = [];
        foreach ($entite->fiche()->resources() as $resource) {
            if (NatureRessource::Photo === $resource->nature()) {
                $ids[] = $resource->damAssetId();
            }
        }

        return $ids;
    }

    /**
     * @param FormInterface<mixed> $form
     * @param list<string>         $existingMediaIds identifiants des photos avant l'édition (les retirées sont supprimées du DAM)
     */
    public function save(Lieu|Restaurant|Activite|ServiceEvenementiel $entite, FormInterface $form, array $existingMediaIds): void
    {
        $fiche = $entite->fiche();
        $images = [];
        $documents = [];
        try {
            foreach ($form->get('ressources') as $resourceForm) {
                $file = $resourceForm->get('image')->getData();
                $resource = $resourceForm->getData();
                if (!$file instanceof UploadedFile || !$resource instanceof RessourceLieu) {
                    continue;
                }
                $media = $this->imageUploader->upload($file, $fiche);
                $images[] = $media;
                $this->entityManager->persist($media);
                $resource->changeDamAssetId($media->id());
                $resource->changeNature(NatureRessource::Photo);
                $this->outbox->enqueue(new MediaUploaded($media->id(), $media->originalStorageKey(), $media->checksum(), ImageVariantRegistry::names()));
            }
            $depots = self::depotsDocumentaires($fiche->type());
            if (null !== $depots) {
                $title = $form->get($depots['titre'])->getData();
                $source = $form->get($depots['source'])->getData();
                foreach ($depots['champs'] as $champ => $usage) {
                    $files = $form->get($champ)->getData();
                    foreach (is_array($files) ? $files : [] as $file) {
                        if (!$file instanceof UploadedFile) {
                            continue;
                        }
                        $asset = $this->documentUploader->upload($file, $fiche, $usage);
                        $documents[] = $asset;
                        $this->entityManager->persist($asset);
                        $resource = new RessourceLieu();
                        $resource->configureDocument($usage);
                        $resource->changeDamAssetId($asset->id());
                        $resource->changeLegende(is_string($title) && '' !== trim($title) ? $title : pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
                        $resource->changeSource(is_string($source) ? $source : null);
                        $entite->addRessource($resource);
                    }
                }
            }
            // Pièces de l'onglet Facturation & partenariat, toutes gammes (un fichier par usage).
            $this->depotAdministratif->deposer($form, $entite, $documents);
            foreach (array_diff($existingMediaIds, $this->photoAssetIds($entite)) as $removed) {
                if ('' !== $removed) {
                    $this->outbox->enqueue(new DeleteMedia($removed));
                }
            }
            $this->entityManager->persist($entite);
            $this->translationScheduler->schedule($fiche);
            $this->outbox->enqueue(new IndexFiche($fiche->idString()));
            // Liaison Lieu ↔ Restaurant modifiée : le payload des fiches
            // détachée/attachée change aussi, sans transition de workflow.
            $this->mutations->enregistrerAvecLiees($entite);
        } catch (\Throwable $exception) {
            $this->cleanup($this->imageUploader->delete(...), $images);
            $this->cleanup($this->documentUploader->delete(...), $documents);
            throw $exception;
        }
    }

    /**
     * @param \Closure(MediaAsset): void $delete
     * @param list<MediaAsset>           $assets
     */
    private function cleanup(\Closure $delete, array $assets): void
    {
        foreach ($assets as $asset) {
            try {
                $delete($asset);
            } catch (\Throwable) {
            }
        }
    }
}
