<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Account\Entity\User;
use App\Account\Security\FicheVoter;
use App\Dam\Entity\MediaAsset;
use App\Dam\Enum\DocumentUsage;
use App\Dam\Message\DeleteMedia;
use App\Dam\Service\FicheDocumentUploader;
use App\Dam\Service\FicheImageUploader;
use App\Dam\Service\ImageVariantRegistry;
use App\Enrichment\Service\FicheTranslationScheduler;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Enum\NatureRessource;
use App\Pim\Enum\StatutFiche;
use App\Pim\Enum\TypeFiche;
use App\Pim\Form\RestaurantSearchType;
use App\Pim\Form\RestaurantType;
use App\Pim\Form\ActiviteDocumentMetadataType;
use App\Pim\Form\LieuDocumentReplaceType;
use App\Pim\Message\IndexFiche;
use App\Pim\ReadModel\FicheCursor;
use App\Pim\Repository\RestaurantRepository;
use App\Pim\Repository\LocalisationRepository;
use App\Pim\Service\FicheCountProvider;
use App\Pim\Validation\ValidationGroups;
use App\Shared\Form\ActionType;
use App\Shared\Message\MediaUploaded;
use App\Shared\Outbox\OutboxPublisherInterface;
use App\Shared\Search\SearchQuery;
use App\Shared\Service\SearchEngineInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/admin/restaurants', name: 'app_pim_restaurant_')]
final class RestaurantController extends AbstractController
{
    public function __construct(
        private readonly FormFactoryInterface $forms,
        private readonly FicheImageUploader $imageUploader,
        private readonly FicheDocumentUploader $documentUploader,
        private readonly LocalisationRepository $locations,
        private readonly FicheTranslationScheduler $translationScheduler,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        RestaurantRepository $repository,
        SearchEngineInterface $search,
        FicheCountProvider $counts,
    ): Response {
        $form = $this->createForm(
            RestaurantSearchType::class,
            [
                'q' => $request->query->getString('q'),
                'status' => StatutFiche::tryFrom(
                    $request->query->getString('status'),
                ),
                'limit' => max(
                    1,
                    min(100, $request->query->getInt('limit', 50)),
                ),
            ],
            ['action' => $this->generateUrl('app_pim_restaurant_index')],
        );
        $form->handleRequest($request);
        if ($form->isSubmitted() && !$form->isValid()) {
            throw new BadRequestHttpException('Critères invalides.');
        }

        $data = $form->getData();
        $status = $data['status'] ?? null;
        $text = trim((string) ($data['q'] ?? ''));
        $limit = max(1, min(100, (int) ($data['limit'] ?? 50)));
        $cursor = $request->query->getString('cursor') ?: null;

        try {
            if ('' !== $text) {
                $filters = ['type' => TypeFiche::Restaurant->value];
                if ($status instanceof StatutFiche) {
                    $filters['status'] = $status->value;
                }

                $page = $search->search(
                    new SearchQuery($text, $filters, $limit, $cursor),
                );
                $items = $repository->findListItemsByIds(
                    array_map(static fn ($result): string => $result->id, $page->results),
                );
                $count = $page->totalCount;
            } else {
                $page = $repository->findListPage(
                    FicheCursor::decode($cursor),
                    $limit,
                    $status instanceof StatutFiche ? $status : null,
                );
                $items = $page->items;
                $count = null;
            }
        } catch (\InvalidArgumentException $exception) {
            throw new BadRequestHttpException(
                $exception->getMessage(),
                $exception,
            );
        }

        return $this->render('pim/restaurant/index.html.twig', [
            'restaurants' => $items,
            'next_cursor' => $page->nextCursor,
            'status' => $status,
            'query' => $text,
            'result_count' => $count,
            'search_form' => $form->createView(),
            'total_count' => $counts->totalByType(TypeFiche::Restaurant),
            'pending_count' => $counts->countByStatus(
                TypeFiche::Restaurant,
                StatutFiche::EnAttenteValidation,
            ),
        ]);
    }

    #[Route('/nouveau', name: 'new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        OutboxPublisherInterface $outbox,
    ): Response {
        return $this->save(
            $request,
            new Restaurant(),
            $entityManager,
            $outbox,
            true,
        );
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], methods: ['GET'])]
    public function show(string $id, RestaurantRepository $repository): Response
    {
        $restaurant = $this->requireRestaurant($id, $repository);
        $this->denyAccessUnlessGranted(FicheVoter::VIEW, $restaurant->fiche());

        return $this->render('pim/restaurant/show.html.twig', [
            'restaurant' => $restaurant,
            'delete_form' => $this->action($restaurant, 'delete', 'Supprimer')->createView(),
            'submit_form' => $this->action($restaurant, 'submit', 'Soumettre à validation')->createView(),
            'validate_form' => $this->action($restaurant, 'validate', 'Valider et publier')->createView(),
            'archive_form' => $this->action($restaurant, 'archive', 'Archiver')->createView(),
            'reject_form' => $this->rejectForm($restaurant)->createView(),
        ]);
    }

    #[Route('/{id}/modifier', name: 'edit', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        string $id,
        RestaurantRepository $repository,
        EntityManagerInterface $entityManager,
        OutboxPublisherInterface $outbox,
    ): Response {
        $restaurant = $this->requireRestaurant($id, $repository);
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $restaurant->fiche());

        return $this->save(
            $request,
            $restaurant,
            $entityManager,
            $outbox,
            false,
        );
    }

    #[Route('/{id}/supprimer', name: 'delete', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], methods: ['POST'])]
    public function delete(
        Request $request,
        string $id,
        RestaurantRepository $repository,
        EntityManagerInterface $entityManager,
    ): Response {
        $restaurant = $this->requireRestaurant($id, $repository);
        $this->denyAccessUnlessGranted(FicheVoter::DELETE, $restaurant->fiche());
        $form = $this->action($restaurant, 'delete', 'Supprimer');
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->remove($restaurant);
            $entityManager->flush();
            $this->addFlash('success', 'Restaurant supprimé.');
        }

        return $this->redirectToRoute('app_pim_restaurant_index');
    }

    #[Route('/{id}/soumettre', name: 'submit', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], methods: ['POST'])]
    public function submit(
        Request $request,
        string $id,
        RestaurantRepository $repository,
        EntityManagerInterface $entityManager,
        OutboxPublisherInterface $outbox,
        ValidatorInterface $validator,
    ): Response {
        $restaurant = $this->requireRestaurant($id, $repository);
        $this->denyAccessUnlessGranted(FicheVoter::SUBMIT, $restaurant->fiche());
        $form = $this->action($restaurant, 'submit', 'Soumettre');
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $errors = $validator->validate($restaurant, null, [
                ValidationGroups::DRAFT,
                ValidationGroups::SUBMISSION,
            ]);
            if (count($errors) > 0) {
                foreach ($errors as $error) {
                    $this->addFlash(
                        'error',
                        $error->getPropertyPath().' : '.$error->getMessage(),
                    );
                }

                return $this->redirectToRoute('app_pim_restaurant_edit', [
                    'id' => $restaurant->id(),
                ]);
            }

            $restaurant->fiche()->submitForValidation($this->actor());
            $outbox->enqueue(new IndexFiche($restaurant->fiche()->idString()));
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_pim_restaurant_show', [
            'id' => $restaurant->id(),
        ]);
    }

    #[Route('/{id}/valider', name: 'validate', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], methods: ['POST'])]
    public function validateRestaurant(
        Request $request,
        string $id,
        RestaurantRepository $repository,
        EntityManagerInterface $entityManager,
        OutboxPublisherInterface $outbox,
    ): Response {
        return $this->transition(
            $request,
            $this->requireRestaurant($id, $repository),
            'validate',
            $entityManager,
            $outbox,
            static fn (Restaurant $restaurant, string $actor) =>
                $restaurant->fiche()->validateAndPublish($actor),
            FicheVoter::VALIDATE,
        );
    }

    #[Route('/{id}/archiver', name: 'archive', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], methods: ['POST'])]
    public function archive(
        Request $request,
        string $id,
        RestaurantRepository $repository,
        EntityManagerInterface $entityManager,
        OutboxPublisherInterface $outbox,
    ): Response {
        return $this->transition(
            $request,
            $this->requireRestaurant($id, $repository),
            'archive',
            $entityManager,
            $outbox,
            static fn (Restaurant $restaurant, string $actor) =>
                $restaurant->fiche()->archive($actor),
            FicheVoter::ARCHIVE,
        );
    }

    #[Route('/{id}/refuser', name: 'reject', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], methods: ['POST'])]
    public function reject(
        Request $request,
        string $id,
        RestaurantRepository $repository,
        EntityManagerInterface $entityManager,
        OutboxPublisherInterface $outbox,
    ): Response {
        $restaurant = $this->requireRestaurant($id, $repository);
        $this->denyAccessUnlessGranted(FicheVoter::VALIDATE, $restaurant->fiche());
        $form = $this->rejectForm($restaurant);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $restaurant->fiche()->rejectValidation(
                $this->actor(),
                (string) $form->get('reason')->getData(),
            );
            $outbox->enqueue(new IndexFiche($restaurant->fiche()->idString()));
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_pim_restaurant_show', [
            'id' => $restaurant->id(),
        ]);
    }

    private function save(
        Request $request,
        Restaurant $restaurant,
        EntityManagerInterface $entityManager,
        OutboxPublisherInterface $outbox,
        bool $creation,
    ): Response {
        $existingMediaIds = $this->photoIds($restaurant);
        $form = $this->createForm(RestaurantType::class, $restaurant);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $uploadedImages = [];
            $uploadedDocuments = [];
            try {
                foreach ($form->get('ressources') as $resourceForm) {
                    $file = $resourceForm->get('image')->getData();
                    $resource = $resourceForm->getData();
                    if (!$file instanceof UploadedFile || !$resource instanceof RessourceLieu) {
                        continue;
                    }

                    $media = $this->imageUploader->upload($file, $restaurant->fiche());
                    $uploadedImages[] = $media;
                    $entityManager->persist($media);
                    $resource->changeDamAssetId($media->id());
                    $resource->changeNature(NatureRessource::Photo);
                    $outbox->enqueue(
                        new MediaUploaded(
                            $media->id(),
                            $media->originalStorageKey(),
                            $media->checksum(),
                            ImageVariantRegistry::names(),
                        ),
                    );
                }

                $this->uploadDocuments(
                    $form,
                    'menus',
                    DocumentUsage::RestaurantMenu,
                    $restaurant,
                    $entityManager,
                    $uploadedDocuments,
                );
                $this->uploadDocuments(
                    $form,
                    'supportsCommerciaux',
                    DocumentUsage::CommercialSupport,
                    $restaurant,
                    $entityManager,
                    $uploadedDocuments,
                );

                foreach (array_diff($existingMediaIds, $this->photoIds($restaurant)) as $removed) {
                    if ('' !== $removed) {
                        $outbox->enqueue(new DeleteMedia($removed));
                    }
                }

                $entityManager->persist($restaurant);
                $outbox->enqueue(new IndexFiche($restaurant->fiche()->idString()));
                $entityManager->flush();
            } catch (\DomainException $exception) {
                $this->cleanupUploads($uploadedImages, $uploadedDocuments);
                $form->addError(new FormError($exception->getMessage()));

                return $this->formResponse($form, $restaurant, $creation);
            } catch (\Throwable $exception) {
                $this->cleanupUploads($uploadedImages, $uploadedDocuments);
                throw $exception;
            }

            $this->addFlash(
                'success',
                $creation ? 'Restaurant créé.' : 'Restaurant modifié.',
            );

            return $this->redirectToRoute('app_pim_restaurant_show', [
                'id' => $restaurant->id(),
            ]);
        }

        return $this->formResponse($form, $restaurant, $creation);
    }

    /**
     * @param FormInterface<Restaurant> $form
     * @param list<MediaAsset>           $uploadedDocuments
     */
    private function uploadDocuments(
        FormInterface $form,
        string $field,
        DocumentUsage $usage,
        Restaurant $restaurant,
        EntityManagerInterface $entityManager,
        array &$uploadedDocuments,
    ): void {
        $files = $form->get($field)->getData();
        foreach (is_array($files) ? $files : [] as $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }

            $asset = $this->documentUploader->upload(
                $file,
                $restaurant->fiche(),
                $usage,
            );
            $uploadedDocuments[] = $asset;
            $entityManager->persist($asset);

            $resource = new RessourceLieu();
            $resource->configureDocument($usage);
            $resource->changeDamAssetId($asset->id());
            $title = $form->get('documentTitle')->getData();
            $resource->changeLegende(
                is_string($title) && '' !== trim($title)
                    ? $title
                    : pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            );
            $source = $form->get('documentSource')->getData();
            $resource->changeSource(is_string($source) ? $source : null);
            if (true === $form->get('documentRightsGranted')->getData()) {
                $resource->grantRights($this->actor());
            }
            $restaurant->addRessource($resource);
        }
    }

    /** @return list<string> */
    private function photoIds(Restaurant $restaurant): array
    {
        return array_map(
            static fn (RessourceLieu $resource): string => $resource->damAssetId(),
            array_values(
                array_filter(
                    $restaurant->ressources()->toArray(),
                    static fn (RessourceLieu $resource): bool =>
                        NatureRessource::Photo === $resource->nature(),
                ),
            ),
        );
    }

    /** @param list<MediaAsset> $images
     *  @param list<MediaAsset> $documents
     */
    private function cleanupUploads(array $images, array $documents): void
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

    /** @param FormInterface<Restaurant> $form */
    private function formResponse(
        FormInterface $form,
        Restaurant $restaurant,
        bool $creation,
    ): Response {
        $documents = [];
        if (!$creation) {
            foreach ($restaurant->ressources() as $resource) {
                if (NatureRessource::Document !== $resource->nature()) {
                    continue;
                }

                $documents[] = [
                    'resource' => $resource,
                    'metadata_form' => $this->forms
                        ->createNamed(
                            'restaurant_document_metadata_'.$resource->id(),
                            ActiviteDocumentMetadataType::class,
                            [
                                'title' => $resource->legende(),
                                'source' => $resource->source(),
                                'rightsGranted' => $resource->rightsGranted(),
                            ],
                            [
                                'action' => $this->generateUrl(
                                    'app_pim_restaurant_document_update',
                                    [
                                        'id' => $restaurant->id(),
                                        'resourceId' => $resource->id(),
                                    ],
                                ),
                                'method' => 'POST',
                            ],
                        )
                        ->createView(),
                    'replace_form' => $this->forms
                        ->createNamed(
                            'restaurant_document_replace_'.$resource->id(),
                            LieuDocumentReplaceType::class,
                            null,
                            [
                                'action' => $this->generateUrl(
                                    'app_pim_restaurant_document_replace',
                                    [
                                        'id' => $restaurant->id(),
                                        'resourceId' => $resource->id(),
                                    ],
                                ),
                                'method' => 'POST',
                            ],
                        )
                        ->createView(),
                    'publication_form' => $this->forms
                        ->createNamed(
                            'restaurant_document_publication_'.$resource->id(),
                            ActionType::class,
                            null,
                            [
                                'action' => $this->generateUrl(
                                    'app_pim_restaurant_document_publication',
                                    [
                                        'id' => $restaurant->id(),
                                        'resourceId' => $resource->id(),
                                    ],
                                ),
                                'button_label' =>
                                    'published' === $resource->publicationStatus()?->value
                                        ? 'Dépublier'
                                        : 'Publier',
                                'csrf_token_id' =>
                                    'restaurant-document-publication-'.$resource->id(),
                            ],
                        )
                        ->createView(),
                    'delete_form' => $this->forms
                        ->createNamed(
                            'restaurant_document_delete_'.$resource->id(),
                            ActionType::class,
                            null,
                            [
                                'action' => $this->generateUrl(
                                    'app_pim_restaurant_document_delete',
                                    [
                                        'id' => $restaurant->id(),
                                        'resourceId' => $resource->id(),
                                    ],
                                ),
                                'button_label' => 'Supprimer',
                                'csrf_token_id' =>
                                    'restaurant-document-delete-'.$resource->id(),
                            ],
                        )
                        ->createView(),
                ];
            }
        }

        return $this->render('pim/restaurant/form.html.twig', [
            'form' => $form->createView(),
            'restaurant' => $restaurant,
            'creation' => $creation,
            'documents' => $documents,
            'duplicate_address_count' => null === $restaurant->localisation()
                ? 0
                : $this->locations->countOtherLocationsWithSameAddress(
                    $restaurant->localisation(),
                ),
        ]);
    }

    private function transition(
        Request $request,
        Restaurant $restaurant,
        string $name,
        EntityManagerInterface $entityManager,
        OutboxPublisherInterface $outbox,
        callable $change,
        string $permission,
    ): Response {
        $this->denyAccessUnlessGranted($permission, $restaurant->fiche());
        $form = $this->action($restaurant, $name, ucfirst($name));
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $change($restaurant, $this->actor());
            $this->translationScheduler->schedule($restaurant->fiche());
            $outbox->enqueue(new IndexFiche($restaurant->fiche()->idString()));
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_pim_restaurant_show', [
            'id' => $restaurant->id(),
        ]);
    }

    private function requireRestaurant(
        string $id,
        RestaurantRepository $repository,
    ): Restaurant {
        $restaurant = $repository->find($id);
        if (!$restaurant instanceof Restaurant) {
            throw $this->createNotFoundException('Restaurant introuvable.');
        }

        return $restaurant;
    }

    /** @return FormInterface<mixed> */
    private function action(
        Restaurant $restaurant,
        string $name,
        string $label,
    ): FormInterface {
        return $this->forms->createNamed(
            $name.'_restaurant',
            ActionType::class,
            null,
            [
                'action' => $this->generateUrl('app_pim_restaurant_'.$name, [
                    'id' => $restaurant->id(),
                ]),
                'button_label' => $label,
                'csrf_token_id' => $name.'-restaurant-'.$restaurant->id(),
            ],
        );
    }

    /** @return FormInterface<mixed> */
    private function rejectForm(Restaurant $restaurant): FormInterface
    {
        return $this->forms
            ->createNamedBuilder('reject_restaurant')
            ->setAction(
                $this->generateUrl('app_pim_restaurant_reject', [
                    'id' => $restaurant->id(),
                ]),
            )
            ->setMethod('POST')
            ->add('reason', TextareaType::class, [
                'label' => 'Motif du refus',
                'required' => true,
            ])
            ->add('submit', SubmitType::class, ['label' => 'Refuser'])
            ->getForm();
    }

    private function actor(): string
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user->id();
    }
}
