<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Account\Entity\User;
use App\Account\Security\FicheVoter;
use App\Dam\Entity\MediaAsset;
use App\Dam\Message\DeleteMedia;
use App\Dam\Message\UnpublishDocument;
use App\Dam\Service\ImageVariantRegistry;
use App\Dam\Service\LieuDocumentPresenter;
use App\Dam\Service\LieuImageUploader;
use App\Dam\Service\LieuPhotoPresenter;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Enum\NatureRessource;
use App\Pim\Enum\StatutFiche;
use App\Pim\Enum\TypeFiche;
use App\Pim\Form\LieuDocumentMetadataType;
use App\Pim\Form\LieuDocumentReplaceType;
use App\Pim\Form\LieuDocumentUploadType;
use App\Pim\Form\LieuPhotoMetadataType;
use App\Pim\Form\LieuPhotoReplaceType;
use App\Pim\Form\LieuPhotoUploadType;
use App\Pim\Form\LieuSearchType;
use App\Pim\Form\LieuType;
use App\Pim\Message\IndexFiche;
use App\Pim\ReadModel\FicheCursor;
use App\Pim\Repository\LieuRepository;
use App\Pim\Validation\ValidationGroups;
use App\Shared\Form\ActionType;
use App\Shared\Message\MediaUploaded;
use App\Shared\Outbox\OutboxPublisherInterface;
use App\Shared\Search\SearchQuery;
use App\Shared\Service\SearchEngineInterface;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/admin/lieux', name: 'app_pim_lieu_')]
final class LieuController extends AbstractController
{
    public function __construct(
        private readonly FormFactoryInterface $forms,
        private readonly LieuImageUploader $imageUploader,
        private readonly LieuPhotoPresenter $photoPresenter,
        private readonly LieuDocumentPresenter $documentPresenter,
        private readonly CsrfTokenManagerInterface $csrfTokens,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        LieuRepository $repository,
        SearchEngineInterface $searchEngine,
    ): Response {
        $searchForm = $this->createForm(
            LieuSearchType::class,
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
            ['action' => $this->generateUrl('app_pim_lieu_index')],
        );
        $searchForm->handleRequest($request);
        if ($searchForm->isSubmitted() && !$searchForm->isValid()) {
            throw new BadRequestHttpException('Critères de recherche invalides.');
        }
        /** @var array{q?: string|null, status?: StatutFiche|null, limit?: int|null} $criteria */
        $criteria = $searchForm->getData();
        $status = $criteria['status'] ?? null;
        $text = trim((string) ($criteria['q'] ?? ''));
        $cursorValue = $request->query->getString('cursor') ?: null;
        $limit = max(1, min(100, (int) ($criteria['limit'] ?? 50)));
        try {
            if ('' !== $text) {
                $filters = ['type' => TypeFiche::Lieu->value];
                if (null !== $status) {
                    $filters['status'] = $status->value;
                }
                $page = $searchEngine->search(
                    new SearchQuery($text, $filters, $limit, $cursorValue),
                );
                $lieux = $repository->findListItemsByIds(
                    array_map(
                        static fn ($result): string => $result->id,
                        $page->results,
                    ),
                );
                $resultCount = $page->totalCount;
            } else {
                $page = $repository->findListPage(
                    FicheCursor::decode($cursorValue),
                    $limit,
                    $status,
                );
                $lieux = $page->items;
                $resultCount = null;
            }
        } catch (\InvalidArgumentException $exception) {
            throw new BadRequestHttpException($exception->getMessage(), $exception);
        }

        return $this->render('pim/lieu/index.html.twig', [
            'lieux' => $lieux,
            'next_cursor' => $page->nextCursor,
            'status' => $status,
            'query' => $text,
            'result_count' => $resultCount,
            'search_form' => $searchForm->createView(),
            'pending_count' => $repository->countByStatus(
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
        $lieu = new Lieu();

        return $this->save($request, $lieu, $entityManager, $outbox, true);
    }

    #[
        Route(
            '/{id}',
            name: 'show',
            requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
            methods: ['GET'],
        ),
    ]
    public function show(string $id, LieuRepository $repository): Response
    {
        $lieu = $this->findLieuOrRedirect($id, $repository);
        if (!($lieu instanceof Lieu)) {
            return $lieu;
        }
        $this->denyAccessUnlessGranted(FicheVoter::VIEW, $lieu->fiche());

        return $this->render('pim/lieu/show.html.twig', [
            'lieu' => $lieu,
            'photos' => $this->photoPresenter->photos($lieu),
            'delete_form' => $this->deleteForm($lieu)->createView(),
            'submit_form' => $this->transitionForm(
                $lieu,
                'submit',
                'Soumettre à validation',
            )->createView(),
            'validate_form' => $this->transitionForm(
                $lieu,
                'validate',
                'Valider et publier',
            )->createView(),
            'archive_form' => $this->transitionForm(
                $lieu,
                'archive',
                'Archiver',
            )->createView(),
            'reject_form' => $this->rejectForm($lieu)->createView(),
        ]);
    }

    #[
        Route(
            '/{id}/modifier',
            name: 'edit',
            requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
            methods: ['GET', 'POST'],
        ),
    ]
    public function edit(
        Request $request,
        string $id,
        LieuRepository $repository,
        EntityManagerInterface $entityManager,
        OutboxPublisherInterface $outbox,
    ): Response {
        $lieu = $this->findLieuOrRedirect($id, $repository);
        if (!($lieu instanceof Lieu)) {
            return $lieu;
        }
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $lieu->fiche());

        return $this->save($request, $lieu, $entityManager, $outbox, false);
    }

    #[
        Route(
            '/{id}/supprimer',
            name: 'delete',
            requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
            methods: ['POST'],
        ),
    ]
    public function delete(
        Request $request,
        string $id,
        LieuRepository $repository,
        EntityManagerInterface $entityManager,
        OutboxPublisherInterface $outbox,
    ): Response {
        $lieu = $this->findLieuOrRedirect($id, $repository);
        if (!($lieu instanceof Lieu)) {
            return $lieu;
        }
        $this->denyAccessUnlessGranted(FicheVoter::DELETE, $lieu->fiche());

        $form = $this->deleteForm($lieu);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            foreach ($lieu->ressources() as $resource) {
                if ('' !== $resource->damAssetId()) {
                    $outbox->enqueue(new DeleteMedia($resource->damAssetId()));
                }
                if (null !== $resource->publicStorageKey()) {
                    $outbox->enqueue(
                        new UnpublishDocument(
                            $resource->id(),
                            $resource->publicStorageKey(),
                        ),
                    );
                }
            }
            $entityManager->remove($lieu);
            $entityManager->flush();
            $this->addFlash('success', 'Lieu supprimé.');
        }

        return $this->redirectToRoute('app_pim_lieu_index');
    }

    #[
        Route(
            '/{id}/soumettre',
            name: 'submit',
            requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
            methods: ['POST'],
        ),
    ]
    public function submit(
        Request $request,
        string $id,
        LieuRepository $repository,
        EntityManagerInterface $entityManager,
        OutboxPublisherInterface $outbox,
        ValidatorInterface $validator,
    ): Response {
        $lieu = $this->requireLieu($id, $repository);
        $this->denyAccessUnlessGranted(FicheVoter::SUBMIT, $lieu->fiche());
        $form = $this->transitionForm(
            $lieu,
            'submit',
            'Soumettre à validation',
        );
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $violations = $validator->validate($lieu, null, [
                ValidationGroups::DRAFT,
                ValidationGroups::SUBMISSION,
            ]);
            if (count($violations) > 0) {
                foreach ($violations as $violation) {
                    $this->addFlash(
                        'error',
                        (string) $violation->getPropertyPath().
                            ' : '.
                            $violation->getMessage(),
                    );
                }

                return $this->redirectToRoute('app_pim_lieu_edit', [
                    'id' => $lieu->id(),
                ]);
            }
            $lieu->fiche()->submitForValidation($this->actorId());
            $outbox->enqueue(new IndexFiche($lieu->fiche()->idString()));
            $entityManager->flush();
            $this->addFlash('success', 'Fiche soumise à validation.');
        }

        return $this->redirectToRoute('app_pim_lieu_show', [
            'id' => $lieu->id(),
        ]);
    }

    #[
        Route(
            '/{id}/valider',
            name: 'validate',
            requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
            methods: ['POST'],
        ),
    ]
    public function validateLieu(
        Request $request,
        string $id,
        LieuRepository $repository,
        EntityManagerInterface $entityManager,
        OutboxPublisherInterface $outbox,
    ): Response {
        $lieu = $this->requireLieu($id, $repository);
        $this->denyAccessUnlessGranted(FicheVoter::VALIDATE, $lieu->fiche());
        $form = $this->transitionForm($lieu, 'validate', 'Valider et publier');
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $lieu->fiche()->validateAndPublish($this->actorId());
            $outbox->enqueue(new IndexFiche($lieu->fiche()->idString()));
            $entityManager->flush();
            $this->addFlash('success', 'Fiche validée et publiée.');
        }

        return $this->redirectToRoute('app_pim_lieu_show', [
            'id' => $lieu->id(),
        ]);
    }

    #[
        Route(
            '/{id}/refuser',
            name: 'reject',
            requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
            methods: ['POST'],
        ),
    ]
    public function reject(
        Request $request,
        string $id,
        LieuRepository $repository,
        EntityManagerInterface $entityManager,
        OutboxPublisherInterface $outbox,
    ): Response {
        $lieu = $this->requireLieu($id, $repository);
        $this->denyAccessUnlessGranted(FicheVoter::VALIDATE, $lieu->fiche());
        $form = $this->rejectForm($lieu);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{reason: string} $data */
            $data = $form->getData();
            $lieu->fiche()->rejectValidation($this->actorId(), $data['reason']);
            $outbox->enqueue(new IndexFiche($lieu->fiche()->idString()));
            $entityManager->flush();
            $this->addFlash('success', 'Fiche refusée et renvoyée en cours.');
        }

        return $this->redirectToRoute('app_pim_lieu_show', [
            'id' => $lieu->id(),
        ]);
    }

    #[
        Route(
            '/{id}/archiver',
            name: 'archive',
            requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
            methods: ['POST'],
        ),
    ]
    public function archive(
        Request $request,
        string $id,
        LieuRepository $repository,
        EntityManagerInterface $entityManager,
        OutboxPublisherInterface $outbox,
    ): Response {
        $lieu = $this->requireLieu($id, $repository);
        $this->denyAccessUnlessGranted(FicheVoter::ARCHIVE, $lieu->fiche());
        $form = $this->transitionForm($lieu, 'archive', 'Archiver');
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            foreach ($lieu->ressources() as $resource) {
                if (NatureRessource::Document !== $resource->nature()) {
                    continue;
                }
                $key = $resource->archiveDocument();
                if (null !== $key) {
                    $outbox->enqueue(
                        new UnpublishDocument($resource->id(), $key),
                    );
                }
            }
            $lieu->fiche()->archive($this->actorId());
            $outbox->enqueue(new IndexFiche($lieu->fiche()->idString()));
            $entityManager->flush();
            $this->addFlash('success', 'Fiche archivée.');
        }

        return $this->redirectToRoute('app_pim_lieu_show', [
            'id' => $lieu->id(),
        ]);
    }

    /** @return \Symfony\Component\Form\FormInterface<mixed> */
    private function deleteForm(
        Lieu $lieu,
    ): \Symfony\Component\Form\FormInterface {
        return $this->forms->createNamed(
            'delete_lieu',
            ActionType::class,
            null,
            [
                'action' => $this->generateUrl('app_pim_lieu_delete', [
                    'id' => $lieu->id(),
                ]),
                'button_label' => 'Supprimer',
                'csrf_token_id' => 'delete-lieu-'.$lieu->id(),
                'attr' => [
                    'data-controller' => 'confirm',
                    'data-confirm-message-value' => 'Supprimer ce lieu ?',
                    'data-action' => 'submit->confirm#submit',
                ],
            ],
        );
    }

    /** @return \Symfony\Component\Form\FormInterface<mixed> */
    private function transitionForm(
        Lieu $lieu,
        string $transition,
        string $label,
    ): \Symfony\Component\Form\FormInterface {
        return $this->forms->createNamed(
            $transition.'_lieu',
            ActionType::class,
            null,
            [
                'action' => $this->generateUrl('app_pim_lieu_'.$transition, [
                    'id' => $lieu->id(),
                ]),
                'button_label' => $label,
                'csrf_token_id' => $transition.'-lieu-'.$lieu->id(),
            ],
        );
    }

    /** @return \Symfony\Component\Form\FormInterface<mixed> */
    private function rejectForm(
        Lieu $lieu,
    ): \Symfony\Component\Form\FormInterface {
        return $this->forms
            ->createNamedBuilder('reject_lieu')
            ->setAction(
                $this->generateUrl('app_pim_lieu_reject', [
                    'id' => $lieu->id(),
                ]),
            )
            ->setMethod('POST')
            ->add('reason', TextareaType::class, [
                'label' => 'Motif du refus',
                'required' => true,
            ])
            ->add(
                'submit',
                \Symfony\Component\Form\Extension\Core\Type\SubmitType::class,
                ['label' => 'Refuser'],
            )
            ->getForm();
    }

    private function actorId(): string
    {
        $user = $this->getUser();
        if (!($user instanceof User)) {
            throw $this->createAccessDeniedException();
        }

        return $user->id();
    }

    private function requireLieu(string $id, LieuRepository $repository): Lieu
    {
        $lieu = $repository->find($id);
        if (!($lieu instanceof Lieu)) {
            throw $this->createNotFoundException('Lieu introuvable.');
        }

        return $lieu;
    }

    private function save(
        Request $request,
        Lieu $lieu,
        EntityManagerInterface $entityManager,
        OutboxPublisherInterface $outbox,
        bool $creation,
    ): Response {
        $existingMediaIds = array_map(
            static fn (
                RessourceLieu $resource,
            ): string => $resource->damAssetId(),
            array_filter(
                $lieu->ressources()->toArray(),
                static fn (
                    RessourceLieu $resource,
                ): bool => NatureRessource::Photo === $resource->nature(),
            ),
        );
        $form = $this->createForm(LieuType::class, $lieu);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var list<MediaAsset> $uploadedMedia */
            $uploadedMedia = [];
            try {
                foreach ($form->get('ressources') as $resourceForm) {
                    $file = $resourceForm->get('image')->getData();
                    if (!($file instanceof UploadedFile)) {
                        continue;
                    }

                    $resource = $resourceForm->getData();
                    if (!($resource instanceof RessourceLieu)) {
                        continue;
                    }

                    $media = $this->imageUploader->upload($file, $lieu);
                    $uploadedMedia[] = $media;
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

                $currentMediaIds = array_map(
                    static fn (
                        RessourceLieu $resource,
                    ): string => $resource->damAssetId(),
                    array_filter(
                        $lieu->ressources()->toArray(),
                        static fn (
                            RessourceLieu $resource,
                        ): bool => NatureRessource::Photo ===
                            $resource->nature(),
                    ),
                );
                foreach (
                    array_diff($existingMediaIds, $currentMediaIds) as $removedMediaId
                ) {
                    if ('' !== $removedMediaId) {
                        $outbox->enqueue(new DeleteMedia($removedMediaId));
                    }
                }

                $entityManager->persist($lieu);
                $outbox->enqueue(new IndexFiche($lieu->fiche()->idString()));
                $entityManager->flush();
            } catch (\DomainException $exception) {
                $this->cleanupUploads($uploadedMedia);
                $form
                    ->get('ressources')
                    ->addError(new FormError($exception->getMessage()));

                return $this->formResponse($form, $lieu, $creation);
            } catch (FilesystemException) {
                $this->cleanupUploads($uploadedMedia);
                $form
                    ->get('ressources')
                    ->addError(
                        new FormError(
                            'Le stockage des médias est temporairement indisponible. Aucun fichier n’a été enregistré.',
                        ),
                    );

                return $this->formResponse($form, $lieu, $creation);
            } catch (\Throwable $exception) {
                $this->cleanupUploads($uploadedMedia);
                throw $exception;
            }

            $this->addFlash(
                'success',
                $creation ? 'Lieu créé.' : 'Lieu modifié.',
            );

            return $this->redirectToRoute('app_pim_lieu_show', [
                'id' => $lieu->id(),
            ]);
        }

        return $this->formResponse($form, $lieu, $creation);
    }

    /** @param \Symfony\Component\Form\FormInterface<mixed> $form */
    private function formResponse(
        \Symfony\Component\Form\FormInterface $form,
        Lieu $lieu,
        bool $creation,
    ): Response {
        $photos = $creation ? [] : $this->photoPresenter->photos($lieu);
        foreach ($photos as &$photo) {
            $resource = $photo['resource'];
            $crop = $resource->crop();
            $photo['metadata_form'] = $this->forms
                ->createNamed(
                    'photo_metadata_'.$resource->id(),
                    LieuPhotoMetadataType::class,
                    [
                        'usage' => $resource->usage(),
                        'legende' => $resource->legende(),
                        'source' => $resource->source(),
                        'salle_id' => $resource->salle(),
                        'rights_granted' => $resource->rightsGranted(),
                        'crop_x' => $crop['x'] ?? null,
                        'crop_y' => $crop['y'] ?? null,
                        'crop_width' => $crop['width'] ?? null,
                        'crop_height' => $crop['height'] ?? null,
                        'rotation' => $resource->rotation(),
                    ],
                    [
                        'action' => $this->generateUrl(
                            'app_pim_lieu_photo_update',
                            [
                                'id' => $lieu->id(),
                                'resourceId' => $resource->id(),
                            ],
                        ),
                        'method' => 'PATCH',
                        'salles' => $lieu->salles()->toArray(),
                    ],
                )
                ->createView();
            $photo['replace_form'] = $this->forms
                ->createNamed(
                    'photo_replace_'.$resource->id(),
                    LieuPhotoReplaceType::class,
                    null,
                    [
                        'action' => $this->generateUrl(
                            'app_pim_lieu_photo_replace',
                            [
                                'id' => $lieu->id(),
                                'resourceId' => $resource->id(),
                            ],
                        ),
                        'method' => 'POST',
                    ],
                )
                ->createView();
        }
        unset($photo);

        $documents = [];
        if (!$creation) {
            foreach ($lieu->ressources() as $resource) {
                if (
                    NatureRessource::Document !== $resource->nature()
                    || null === $resource->documentUsage()
                ) {
                    continue;
                }
                $documents[] = [
                    'view' => $this->documentPresenter->resource($resource),
                    'metadata_form' => $this->forms
                        ->createNamed(
                            'document_metadata_'.$resource->id(),
                            LieuDocumentMetadataType::class,
                            [
                                'usage' => $resource->documentUsage(),
                                'salle' => $resource->salle(),
                                'title' => $resource->legende(),
                                'source' => $resource->source(),
                                'rightsGranted' => $resource->rightsGranted(),
                            ],
                            [
                                'action' => $this->generateUrl(
                                    'app_pim_lieu_document_update',
                                    [
                                        'id' => $lieu->id(),
                                        'resourceId' => $resource->id(),
                                    ],
                                ),
                                'method' => 'POST',
                                'salles' => $lieu->salles()->toArray(),
                            ],
                        )
                        ->createView(),
                    'replace_form' => $this->forms
                        ->createNamed(
                            'document_replace_'.$resource->id(),
                            LieuDocumentReplaceType::class,
                            null,
                            [
                                'action' => $this->generateUrl(
                                    'app_pim_lieu_document_replace',
                                    [
                                        'id' => $lieu->id(),
                                        'resourceId' => $resource->id(),
                                    ],
                                ),
                                'method' => 'POST',
                            ],
                        )
                        ->createView(),
                    'publication_form' => $this->forms
                        ->createNamed(
                            'document_publication_'.$resource->id(),
                            ActionType::class,
                            null,
                            [
                                'action' => $this->generateUrl(
                                    'app_pim_lieu_document_publication',
                                    [
                                        'id' => $lieu->id(),
                                        'resourceId' => $resource->id(),
                                    ],
                                ),
                                'button_label' => 'published' ===
                                    $resource->publicationStatus()?->value
                                        ? 'Dépublier'
                                        : 'Publier',
                                'csrf_token_id' => 'document-publication-'.$resource->id(),
                            ],
                        )
                        ->createView(),
                    'delete_form' => $this->forms
                        ->createNamed(
                            'document_delete_'.$resource->id(),
                            ActionType::class,
                            null,
                            [
                                'action' => $this->generateUrl(
                                    'app_pim_lieu_document_delete',
                                    [
                                        'id' => $lieu->id(),
                                        'resourceId' => $resource->id(),
                                    ],
                                ),
                                'button_label' => 'Supprimer',
                                'csrf_token_id' => 'document-delete-'.$resource->id(),
                                'attr' => [
                                    'data-controller' => 'confirm',
                                    'data-confirm-message-value' => 'Supprimer ce document ?',
                                    'data-action' => 'submit->confirm#submit',
                                ],
                            ],
                        )
                        ->createView(),
                ];
            }
        }

        return $this->render('pim/lieu/form.html.twig', [
            'form' => $form,
            'lieu' => $lieu,
            'creation' => $creation,
            'photos' => $photos,
            'documents' => $documents,
            'document_upload_form' => $creation
                ? null
                : $this->forms
                    ->createNamed(
                        'document_upload',
                        LieuDocumentUploadType::class,
                        null,
                        [
                            'action' => $this->generateUrl(
                                'app_pim_lieu_document_upload',
                                ['id' => $lieu->id()],
                            ),
                            'method' => 'POST',
                            'salles' => $lieu->salles()->toArray(),
                        ],
                    )
                    ->createView(),
            'media_upload_form' => $creation
                ? null
                : $this->forms
                    ->createNamed(
                        'lieu_photo_upload',
                        LieuPhotoUploadType::class,
                        null,
                        [
                            'action' => $this->generateUrl(
                                'app_pim_lieu_photo_upload',
                                ['id' => $lieu->id()],
                            ),
                            'method' => 'POST',
                        ],
                    )
                    ->createView(),
            'media_csrf_token' => $creation
                ? null
                : $this->csrfTokens
                    ->getToken('lieu-media-'.$lieu->id())
                    ->getValue(),
        ]);
    }

    /** @param list<MediaAsset> $media */
    private function cleanupUploads(array $media): void
    {
        foreach ($media as $asset) {
            try {
                $this->imageUploader->delete($asset);
            } catch (\Throwable) {
                // The original exception remains the source of truth; orphan cleanup can be retried operationally.
            }
        }
    }

    private function findLieuOrRedirect(
        string $id,
        LieuRepository $repository,
    ): Lieu|Response {
        $lieu = $repository->find($id);
        if ($lieu instanceof Lieu) {
            return $lieu;
        }

        $this->addFlash(
            'warning',
            'Ce lieu n’existe plus ou vient d’être supprimé.',
        );

        return $this->redirectToRoute('app_pim_lieu_index');
    }
}
