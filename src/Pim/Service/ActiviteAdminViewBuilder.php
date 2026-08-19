<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Dam\Enum\DocumentUsage;
use App\Pim\Entity\Activite\Activite;
use App\Pim\Enum\NatureRessource;
use App\Pim\Form\ActiviteDocumentMetadataType;
use App\Pim\Form\LieuDocumentReplaceType;
use App\Shared\Form\ActionType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class ActiviteAdminViewBuilder
{
    public function __construct(private FormFactoryInterface $forms, private UrlGeneratorInterface $urls) {}

    /** @param FormInterface<mixed> $form
     *  @return array<string, mixed>
     */
    public function form(FormInterface $form, Activite $activite, bool $creation): array
    {
        return ['form' => $form, 'activite' => $activite, 'creation' => $creation, 'documents' => $creation ? [] : $this->documents($activite)];
    }

    /** @return list<array<string, mixed>> Documents et leurs formulaires de modale — re-servis seuls par le bloc médias. */
    public function documents(Activite $activite): array
    {
        $documents = [];
        foreach ($activite->ressources() as $resource) {
            if (NatureRessource::Document !== $resource->nature() || DocumentUsage::CommercialSupport !== $resource->documentUsage()) {
                continue;
            }
            $routeParameters = ['id' => $activite->id(), 'resourceId' => $resource->id()];
            $documents[] = [
                'resource' => $resource,
                'metadata_form' => $this->forms->createNamed('activite_document_metadata_'.$resource->id(), ActiviteDocumentMetadataType::class, [
                    'title' => $resource->legende(), 'source' => $resource->source(), 'keywords' => $resource->keywords(), 'rightsExpiresAt' => $resource->rightsExpiresAt(),
                ], ['action' => $this->urls->generate('app_pim_activite_document_update', $routeParameters), 'method' => 'POST'])->createView(),
                'replace_form' => $this->forms->createNamed('activite_document_replace_'.$resource->id(), LieuDocumentReplaceType::class, null, [
                    'action' => $this->urls->generate('app_pim_activite_document_replace', $routeParameters), 'method' => 'POST',
                ])->createView(),
                'publication_form' => $this->forms->createNamed('activite_document_publication_'.$resource->id(), ActionType::class, null, [
                    'action' => $this->urls->generate('app_pim_activite_document_publication', $routeParameters),
                    'button_label' => 'published' === $resource->publicationStatus()?->value ? 'Dépublier' : 'Publier',
                    'csrf_token_id' => 'activite-document-publication-'.$resource->id(),
                ])->createView(),
                'delete_form' => $this->forms->createNamed('activite_document_delete_'.$resource->id(), ActionType::class, null, [
                    'action' => $this->urls->generate('app_pim_activite_document_delete', $routeParameters),
                    'button_label' => 'Supprimer', 'csrf_token_id' => 'activite-document-delete-'.$resource->id(),
                ])->createView(),
            ];
        }

        return $documents;
    }
}
