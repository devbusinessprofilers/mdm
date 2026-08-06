<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Enum\NatureRessource;
use App\Pim\Form\ActiviteDocumentMetadataType;
use App\Pim\Form\LieuDocumentReplaceType;
use App\Pim\Repository\LocalisationRepository;
use App\Shared\Form\ActionType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class RestaurantAdminViewBuilder
{
    public function __construct(
        private FormFactoryInterface $forms,
        private UrlGeneratorInterface $urls,
        private LocalisationRepository $locations,
    ) {}

    /** @param FormInterface<mixed> $form
     *  @return array<string, mixed>
     */
    public function form(FormInterface $form, Restaurant $restaurant, bool $creation): array
    {
        $documents = [];
        if (!$creation) {
            foreach ($restaurant->ressources() as $resource) {
                if (NatureRessource::Document !== $resource->nature()) { continue; }
                $params = ['id' => $restaurant->id(), 'resourceId' => $resource->id()];
                $documents[] = [
                    'resource' => $resource,
                    'metadata_form' => $this->forms->createNamed('restaurant_document_metadata_'.$resource->id(), ActiviteDocumentMetadataType::class, [
                        'title' => $resource->legende(), 'source' => $resource->source(), 'keywords' => $resource->keywords(), 'rightsExpiresAt' => $resource->rightsExpiresAt(),
                    ], ['action' => $this->urls->generate('app_pim_restaurant_document_update', $params), 'method' => 'POST'])->createView(),
                    'replace_form' => $this->forms->createNamed('restaurant_document_replace_'.$resource->id(), LieuDocumentReplaceType::class, null, [
                        'action' => $this->urls->generate('app_pim_restaurant_document_replace', $params), 'method' => 'POST',
                    ])->createView(),
                    'publication_form' => $this->forms->createNamed('restaurant_document_publication_'.$resource->id(), ActionType::class, null, [
                        'action' => $this->urls->generate('app_pim_restaurant_document_publication', $params),
                        'button_label' => 'published' === $resource->publicationStatus()?->value ? 'Dépublier' : 'Publier',
                        'csrf_token_id' => 'restaurant-document-publication-'.$resource->id(),
                    ])->createView(),
                    'delete_form' => $this->forms->createNamed('restaurant_document_delete_'.$resource->id(), ActionType::class, null, [
                        'action' => $this->urls->generate('app_pim_restaurant_document_delete', $params),
                        'button_label' => 'Supprimer', 'csrf_token_id' => 'restaurant-document-delete-'.$resource->id(),
                    ])->createView(),
                ];
            }
        }

        return [
            'form' => $form->createView(), 'restaurant' => $restaurant, 'creation' => $creation, 'documents' => $documents,
            'duplicate_address_count' => null === $restaurant->localisation() ? 0 : $this->locations->countOtherLocationsWithSameAddress($restaurant->localisation()),
        ];
    }
}
