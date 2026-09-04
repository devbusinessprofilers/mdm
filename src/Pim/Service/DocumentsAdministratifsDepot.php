<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Dam\Entity\MediaAsset;
use App\Dam\Message\DeleteMedia;
use App\Dam\Message\UnpublishDocument;
use App\Dam\Service\FicheDocumentUploader;
use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Pim\Form\FicheFormCatalog;
use App\Shared\Outbox\OutboxPublisherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Pièces jointes de l'onglet Facturation & partenariat (URSSAF, RC pro, RIB,
 * RIB d'affacturage, convention, CGV) déposées par le formulaire principal
 * de la fiche, toutes gammes : un fichier par usage, le nouveau remplace
 * l'ancien (document DAM privé, ancien fichier dépublié puis supprimé).
 */
final readonly class DocumentsAdministratifsDepot
{
    public function __construct(
        private FicheDocumentUploader $uploader,
        private EntityManagerInterface $entityManager,
        private OutboxPublisherInterface $outbox,
    ) {
    }

    /**
     * @param FormInterface<mixed> $form
     * @param list<MediaAsset>     $deposes fichiers téléversés (nettoyage par l'appelant en cas d'échec)
     */
    public function deposer(FormInterface $form, Lieu|Restaurant|Activite|ServiceEvenementiel $entite, array &$deposes): void
    {
        foreach (FicheFormCatalog::FICHIERS as $champ => [, $usage]) {
            if (!$form->has($champ)) {
                continue;
            }
            $fichier = $form->get($champ)->getData();
            if (is_array($fichier)) {
                $fichier = $fichier[0] ?? null;
            }
            if (!$fichier instanceof UploadedFile) {
                continue;
            }
            foreach ($entite->ressources()->toArray() as $existant) {
                if ($existant->usage() === $usage->value) {
                    $cle = $existant->requestUnpublication();
                    if (null !== $cle) {
                        $this->outbox->enqueue(new UnpublishDocument($existant->id(), $cle));
                    }
                    $this->outbox->enqueue(new DeleteMedia($existant->damAssetId()));
                    $entite->removeRessource($existant);
                    $this->entityManager->remove($existant);
                }
            }
            $asset = $this->uploader->upload($fichier, $entite->fiche(), $usage);
            $deposes[] = $asset;
            $this->entityManager->persist($asset);
            $document = new RessourceLieu();
            $document->configureDocument($usage);
            $document->changeDamAssetId($asset->id());
            $document->changeLegende(pathinfo($fichier->getClientOriginalName(), PATHINFO_FILENAME));
            $entite->addRessource($document);
        }
    }

    /** @param list<MediaAsset> $deposes */
    public function nettoyer(array $deposes): void
    {
        foreach ($deposes as $asset) {
            try {
                $this->uploader->delete($asset);
            } catch (\Throwable) {
            }
        }
    }
}
