<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Dam\Service\LieuDocumentPresenter;
use App\Dam\Service\LieuPhotoPresenter;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Enum\NatureRessource;
use App\Pim\Form\LieuDocumentUploadType;
use App\Pim\Form\LieuPhotoUploadType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final readonly class LieuAdminViewBuilder
{
    public function __construct(
        private FormFactoryInterface $forms,
        private UrlGeneratorInterface $urls,
        private LieuPhotoPresenter $photos,
        private LieuDocumentPresenter $documents,
        private DocumentsModalesVue $documentsModales,
        private PhotosModalesVue $photosModales,
        private CsrfTokenManagerInterface $csrfTokens,
    ) {
    }

    /** @param FormInterface<mixed> $form
     * @return array<string, mixed>
     */
    public function form(FormInterface $form, Lieu $lieu, bool $creation): array
    {
        return ['form' => $form, 'lieu' => $lieu, 'creation' => $creation] + ($creation
            ? ['photos' => [], 'documents' => [], 'document_upload_forms' => [], 'media_upload_form' => null, 'media_csrf_token' => null]
            : $this->mediasVars($lieu));
    }

    /**
     * Variables du bloc médias (galerie + tuiles documents), rendues dans
     * l'éditeur et re-servies seules par app_pim_fiche_medias_bloc après chaque
     * action média — le bloc se rafraîchit sans recharger la page.
     * La page ne rend que les vignettes : les modales (et leurs formulaires,
     * coûteux à construire par photo/document) sont servies par
     * app_pim_fiche_photo_modales et préchargées en arrière-plan après le
     * chargement — voir modalesVars().
     *
     * @return array<string, mixed>
     */
    public function mediasVars(Lieu $lieu): array
    {
        $documents = [];
        foreach ($lieu->ressources() as $resource) {
            if (NatureRessource::Document !== $resource->nature() || null === $resource->documentUsage()) {
                continue;
            }
            $documents[] = ['view' => $this->documents->resource($resource), 'onglet' => $resource->documentUsage()->ongletMedia()];
        }

        // Un formulaire de dépôt par onglet du volet Médias : les usages
        // proposés sont filtrés selon l'onglet (plans, supports, autres).
        $uploadForms = [];
        foreach (ProfilDocumentsGamme::ONGLETS_DEPOT_LIEU as $onglet => $usages) {
            $uploadForms[$onglet] = $this->forms->createNamed('document_upload_'.$onglet, LieuDocumentUploadType::class, null, [
                'action' => $this->urls->generate('app_pim_fiche_document_upload', ['gamme' => 'lieux', 'id' => $lieu->id()]), 'method' => 'POST',
                'salles' => $lieu->salles()->toArray(), 'usages' => $usages,
            ])->createView();
        }

        $salles = [];
        foreach ($lieu->salles() as $salle) {
            $salles[$salle->id()] = $salle->nom();
        }

        return [
            'photos' => $this->photos->photos($lieu), 'documents' => $documents,
            'salles' => $salles,
            'document_upload_forms' => $uploadForms,
            'media_upload_form' => $this->forms->createNamed('lieu_photo_upload', LieuPhotoUploadType::class, null, [
                'action' => $this->urls->generate('app_pim_fiche_photo_upload', ['gamme' => 'lieux', 'id' => $lieu->id()]), 'method' => 'POST',
            ])->createView(),
            'media_csrf_token' => $this->csrfTokens->getToken('lieu-media-'.$lieu->id())->getValue(),
        ];
    }

    /**
     * Variables des modales de paramètres des photos et documents, rendues par
     * app_pim_fiche_photo_modales et préchargées après le chargement de la page.
     *
     * @return array<string, mixed>
     */
    public function modalesVars(Lieu $lieu): array
    {
        $photos = $this->photosModales->photos($lieu);

        // Les formulaires des modales sont ceux de toutes les gammes ; la vue
        // du Lieu y ajoute la présentation du document (accès, statut, URL publique).
        $documents = [];
        foreach ($this->documentsModales->documents($lieu) as $document) {
            $documents[] = ['view' => $this->documents->resource($document['resource'])] + $document;
        }

        return [
            'lieu' => $lieu, 'documents' => $documents,
        ] + $this->photosModales->variables($lieu);
    }
}
