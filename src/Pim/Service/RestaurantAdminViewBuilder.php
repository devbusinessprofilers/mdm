<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Enum\NatureRessource;
use App\Pim\Form\LieuDocumentReplaceType;
use App\Pim\Form\RestaurantDocumentMetadataType;
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
        return [
            'form' => $form->createView(), 'restaurant' => $restaurant, 'creation' => $creation,
            'documents' => $creation ? [] : $this->documents($restaurant),
            'duplicate_address_count' => null === $restaurant->localisation() ? 0 : $this->locations->countOtherLocationsWithSameAddress($restaurant->localisation()),
        ];
    }

    /** @return list<array<string, mixed>> Documents et leurs formulaires de modale — re-servis seuls par le bloc médias. */
    public function documents(Restaurant $restaurant): array
    {
        $documents = [];
        foreach ($restaurant->ressources() as $resource) {
            if (NatureRessource::Document !== $resource->nature()) { continue; }
            $params = ['id' => $restaurant->id(), 'resourceId' => $resource->id()];
            $documents[] = [
                'resource' => $resource,
                'metadata_form' => $this->forms->createNamed('restaurant_document_metadata_'.$resource->id(), RestaurantDocumentMetadataType::class, [
                    'title' => $resource->legende(), 'source' => $resource->source(), 'keywords' => $resource->keywords(),
                    'rightsExpiresAt' => $resource->rightsExpiresAt(), 'salle' => $resource->restaurantSalle(),
                ], ['action' => $this->urls->generate('app_pim_restaurant_document_update', $params), 'method' => 'POST', 'salles' => $restaurant->salles()->toArray()])->createView(),
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

        return $documents;
    }
}
