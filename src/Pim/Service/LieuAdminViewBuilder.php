<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Dam\Service\LieuDocumentPresenter;
use App\Dam\Service\LieuPhotoPresenter;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Enum\NatureRessource;
use App\Pim\Form\LieuDocumentMetadataType;
use App\Pim\Form\LieuDocumentReplaceType;
use App\Pim\Form\LieuDocumentUploadType;
use App\Pim\Form\LieuPhotoMetadataType;
use App\Pim\Form\LieuPhotoReplaceType;
use App\Pim\Form\LieuPhotoUploadType;
use App\Shared\Form\ActionType;
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
        private CsrfTokenManagerInterface $csrfTokens,
    ) {}

    /** @param FormInterface<mixed> $form
     *  @return array<string, mixed>
     */
    public function form(FormInterface $form, Lieu $lieu, bool $creation): array
    {
        return ['form' => $form, 'lieu' => $lieu, 'creation' => $creation] + ($creation
            ? ['photos' => [], 'documents' => [], 'document_upload_form' => null, 'media_upload_form' => null, 'media_csrf_token' => null]
            : $this->mediasVars($lieu));
    }

    /**
     * Variables du bloc médias (galerie + tuiles documents), rendues dans
     * l'éditeur et re-servies seules par app_pim_lieu_medias_bloc après chaque
     * action média — le bloc se rafraîchit sans recharger la page.
     * La page ne rend que les vignettes : les modales (et leurs formulaires,
     * coûteux à construire par photo/document) sont servies par
     * app_pim_lieu_media_modales et préchargées en arrière-plan après le
     * chargement — voir modalesVars().
     *
     * @return array<string, mixed>
     */
    public function mediasVars(Lieu $lieu): array
    {
        $documents = [];
        foreach ($lieu->ressources() as $resource) {
            if (NatureRessource::Document !== $resource->nature() || null === $resource->documentUsage()) { continue; }
            $documents[] = ['view' => $this->documents->resource($resource)];
        }

        return [
            'photos' => $this->photos->photos($lieu), 'documents' => $documents,
            'document_upload_form' => $this->forms->createNamed('document_upload', LieuDocumentUploadType::class, null, [
                'action' => $this->urls->generate('app_pim_lieu_document_upload', ['id' => $lieu->id()]), 'method' => 'POST', 'salles' => $lieu->salles()->toArray(),
            ])->createView(),
            'media_upload_form' => $this->forms->createNamed('lieu_photo_upload', LieuPhotoUploadType::class, null, [
                'action' => $this->urls->generate('app_pim_lieu_photo_upload', ['id' => $lieu->id()]), 'method' => 'POST',
            ])->createView(),
            'media_csrf_token' => $this->csrfTokens->getToken('lieu-media-'.$lieu->id())->getValue(),
        ];
    }

    /**
     * Variables des modales de paramètres des photos et documents, rendues par
     * app_pim_lieu_media_modales et préchargées après le chargement de la page.
     *
     * @return array<string, mixed>
     */
    public function modalesVars(Lieu $lieu): array
    {
        $photos = $this->photos->photos($lieu);
        foreach ($photos as &$photo) {
            $resource = $photo['resource'];
            $crop = $resource->crop();
            $params = ['id' => $lieu->id(), 'resourceId' => $resource->id()];
            $photo['metadata_form'] = $this->forms->createNamed('photo_metadata_'.$resource->id(), LieuPhotoMetadataType::class, [
                'usage' => $resource->usage(), 'legende' => $resource->legende(), 'source' => $resource->source(),
                'keywords' => $resource->keywords(), 'rights_expires_at' => $resource->rightsExpiresAt(), 'salle_id' => $resource->salle(),
                'crop_x' => $crop['x'] ?? null, 'crop_y' => $crop['y'] ?? null, 'crop_width' => $crop['width'] ?? null,
                'crop_height' => $crop['height'] ?? null, 'rotation' => $resource->rotation(),
            ], [
                'action' => $this->urls->generate('app_pim_lieu_photo_update', $params), 'method' => 'PATCH', 'salles' => $lieu->salles()->toArray(),
            ])->createView();
            $photo['replace_form'] = $this->forms->createNamed('photo_replace_'.$resource->id(), LieuPhotoReplaceType::class, null, [
                'action' => $this->urls->generate('app_pim_lieu_photo_replace', $params), 'method' => 'POST',
            ])->createView();
        }
        unset($photo);

        $documents = [];
        foreach ($lieu->ressources() as $resource) {
            if (NatureRessource::Document !== $resource->nature() || null === $resource->documentUsage()) { continue; }
            $params = ['id' => $lieu->id(), 'resourceId' => $resource->id()];
            $documents[] = [
                'view' => $this->documents->resource($resource),
                'metadata_form' => $this->forms->createNamed('document_metadata_'.$resource->id(), LieuDocumentMetadataType::class, [
                    'usage' => $resource->documentUsage(), 'salle' => $resource->salle(), 'title' => $resource->legende(),
                    'source' => $resource->source(), 'keywords' => $resource->keywords(), 'rightsExpiresAt' => $resource->rightsExpiresAt(),
                ], ['action' => $this->urls->generate('app_pim_lieu_document_update', $params), 'method' => 'POST', 'salles' => $lieu->salles()->toArray()])->createView(),
                'replace_form' => $this->forms->createNamed('document_replace_'.$resource->id(), LieuDocumentReplaceType::class, null, [
                    'action' => $this->urls->generate('app_pim_lieu_document_replace', $params), 'method' => 'POST',
                ])->createView(),
                'publication_form' => $this->forms->createNamed('document_publication_'.$resource->id(), ActionType::class, null, [
                    'action' => $this->urls->generate('app_pim_lieu_document_publication', $params),
                    'button_label' => 'published' === $resource->publicationStatus()?->value ? 'Dépublier' : 'Publier',
                    'csrf_token_id' => 'document-publication-'.$resource->id(),
                ])->createView(),
                'delete_form' => $this->forms->createNamed('document_delete_'.$resource->id(), ActionType::class, null, [
                    'action' => $this->urls->generate('app_pim_lieu_document_delete', $params), 'button_label' => 'Supprimer',
                    'csrf_token_id' => 'document-delete-'.$resource->id(),
                    'attr' => ['data-controller' => 'confirm', 'data-confirm-message-value' => 'Supprimer ce document ?', 'data-action' => 'submit->confirm#submit'],
                ])->createView(),
            ];
        }

        return ['lieu' => $lieu, 'photos' => $photos, 'documents' => $documents];
    }
}
