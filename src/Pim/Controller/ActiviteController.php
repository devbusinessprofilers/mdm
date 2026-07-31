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
use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Enum\NatureRessource;
use App\Pim\Enum\StatutFiche;
use App\Pim\Enum\TypeFiche;
use App\Pim\Form\ActiviteDocumentMetadataType;
use App\Pim\Form\ActiviteSearchType;
use App\Pim\Form\ActiviteType;
use App\Pim\Form\LieuDocumentReplaceType;
use App\Pim\Message\IndexFiche;
use App\Pim\ReadModel\FicheCursor;
use App\Pim\Repository\ActiviteRepository;
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
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/admin/activites', name: 'app_pim_activite_')]
final class ActiviteController extends AbstractController
{
    public function __construct(
        private readonly FormFactoryInterface $forms,
        private readonly FicheImageUploader $imageUploader,
        private readonly FicheDocumentUploader $documentUploader,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        ActiviteRepository $repo,
        SearchEngineInterface $search,
    ): Response {
        $form = $this->createForm(
            ActiviteSearchType::class,
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
            ['action' => $this->generateUrl('app_pim_activite_index')],
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
                $filters = ['type' => TypeFiche::Activite->value];
                if ($status instanceof StatutFiche) {
                    $filters['status'] = $status->value;
                }
                $page = $search->search(
                    new SearchQuery($text, $filters, $limit, $cursor),
                );
                $items = $repo->findListItemsByIds(
                    array_map(static fn ($r): string => $r->id, $page->results),
                );
                $count = $page->totalCount;
            } else {
                $page = $repo->findListPage(
                    FicheCursor::decode($cursor),
                    $limit,
                    $status,
                );
                $items = $page->items;
                $count = null;
            }
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage(), $e);
        }

        return $this->render('pim/activite/index.html.twig', [
            'activites' => $items,
            'next_cursor' => $page->nextCursor,
            'status' => $status,
            'query' => $text,
            'result_count' => $count,
            'search_form' => $form->createView(),
            'pending_count' => $repo->countByStatus(
                StatutFiche::EnAttenteValidation,
            ),
        ]);
    }

    #[Route('/nouveau', name: 'new', methods: ['GET', 'POST'])]
    public function new(
        Request $r,
        EntityManagerInterface $em,
        OutboxPublisherInterface $o,
    ): Response {
        return $this->save($r, new Activite(), $em, $o, true);
    }

    #[
        Route(
            '/{id}',
            name: 'show',
            requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
            methods: ['GET'],
        ),
    ]
    public function show(string $id, ActiviteRepository $repo): Response
    {
        $a = $this->require($id, $repo);
        $this->denyAccessUnlessGranted(FicheVoter::VIEW, $a->fiche());

        return $this->render('pim/activite/show.html.twig', [
            'activite' => $a,
            'delete_form' => $this->action(
                $a,
                'delete',
                'Supprimer',
            )->createView(),
            'submit_form' => $this->action(
                $a,
                'submit',
                'Soumettre à validation',
            )->createView(),
            'validate_form' => $this->action(
                $a,
                'validate',
                'Valider et publier',
            )->createView(),
            'archive_form' => $this->action(
                $a,
                'archive',
                'Archiver',
            )->createView(),
            'reject_form' => $this->rejectForm($a)->createView(),
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
        Request $r,
        string $id,
        ActiviteRepository $repo,
        EntityManagerInterface $em,
        OutboxPublisherInterface $o,
    ): Response {
        $a = $this->require($id, $repo);
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $a->fiche());

        return $this->save($r, $a, $em, $o, false);
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
        Request $r,
        string $id,
        ActiviteRepository $repo,
        EntityManagerInterface $em,
    ): Response {
        $a = $this->require($id, $repo);
        $this->denyAccessUnlessGranted(FicheVoter::DELETE, $a->fiche());
        $f = $this->action($a, 'delete', 'Supprimer');
        $f->handleRequest($r);
        if ($f->isSubmitted() && $f->isValid()) {
            $em->remove($a);
            $em->flush();
            $this->addFlash('success', 'Activité supprimée.');
        }

        return $this->redirectToRoute('app_pim_activite_index');
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
        Request $r,
        string $id,
        ActiviteRepository $repo,
        EntityManagerInterface $em,
        OutboxPublisherInterface $o,
        ValidatorInterface $v,
    ): Response {
        $a = $this->require($id, $repo);
        $this->denyAccessUnlessGranted(FicheVoter::SUBMIT, $a->fiche());
        $f = $this->action($a, 'submit', 'Soumettre');
        $f->handleRequest($r);
        if ($f->isSubmitted() && $f->isValid()) {
            $errors = $v->validate($a, null, [
                ValidationGroups::DRAFT,
                ValidationGroups::SUBMISSION,
            ]);
            if (count($errors)) {
                foreach ($errors as $error) {
                    $this->addFlash(
                        'error',
                        $error->getPropertyPath().
                            ' : '.
                            $error->getMessage(),
                    );
                }

                return $this->redirectToRoute('app_pim_activite_edit', [
                    'id' => $a->id(),
                ]);
            }
            $a->fiche()->submitForValidation($this->actor());
            $o->enqueue(new IndexFiche($a->fiche()->idString()));
            $em->flush();
        }

        return $this->redirectToRoute('app_pim_activite_show', [
            'id' => $a->id(),
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
    public function validateActivity(
        Request $r,
        string $id,
        ActiviteRepository $repo,
        EntityManagerInterface $em,
        OutboxPublisherInterface $o,
    ): Response {
        return $this->transition(
            $r,
            $this->require($id, $repo),
            'validate',
            $em,
            $o,
            static fn (Activite $a, string $actor) => $a
                ->fiche()
                ->validateAndPublish($actor),
            FicheVoter::VALIDATE,
        );
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
        Request $r,
        string $id,
        ActiviteRepository $repo,
        EntityManagerInterface $em,
        OutboxPublisherInterface $o,
    ): Response {
        return $this->transition(
            $r,
            $this->require($id, $repo),
            'archive',
            $em,
            $o,
            static fn (Activite $a, string $actor) => $a
                ->fiche()
                ->archive($actor),
            FicheVoter::ARCHIVE,
        );
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
        Request $r,
        string $id,
        ActiviteRepository $repo,
        EntityManagerInterface $em,
        OutboxPublisherInterface $o,
    ): Response {
        $a = $this->require($id, $repo);
        $this->denyAccessUnlessGranted(FicheVoter::VALIDATE, $a->fiche());
        $f = $this->rejectForm($a);
        $f->handleRequest($r);
        if ($f->isSubmitted() && $f->isValid()) {
            $a->fiche()->rejectValidation(
                $this->actor(),
                (string) $f->get('reason')->getData(),
            );
            $o->enqueue(new IndexFiche($a->fiche()->idString()));
            $em->flush();
        }

        return $this->redirectToRoute('app_pim_activite_show', [
            'id' => $a->id(),
        ]);
    }

    private function save(
        Request $r,
        Activite $a,
        EntityManagerInterface $em,
        OutboxPublisherInterface $o,
        bool $creation,
    ): Response {
        $existingMediaIds = array_map(
            static fn (
                RessourceLieu $resource,
            ): string => $resource->damAssetId(),
            array_filter(
                $a->ressources()->toArray(),
                static fn (
                    RessourceLieu $resource,
                ): bool => NatureRessource::Photo === $resource->nature(),
            ),
        );
        $f = $this->createForm(ActiviteType::class, $a);
        $f->handleRequest($r);
        if ($f->isSubmitted() && $f->isValid()) {
            $uploaded = [];
            $uploadedDocuments = [];
            try {
                foreach ($f->get('ressources') as $resourceForm) {
                    $file = $resourceForm->get('image')->getData();
                    $resource = $resourceForm->getData();
                    if (
                        !($file instanceof UploadedFile)
                        || !($resource instanceof RessourceLieu)
                    ) {
                        continue;
                    }
                    $media = $this->imageUploader->upload($file, $a->fiche());
                    $uploaded[] = $media;
                    $em->persist($media);
                    $resource->changeDamAssetId($media->id());
                    $resource->changeNature(NatureRessource::Photo);
                    $o->enqueue(
                        new MediaUploaded(
                            $media->id(),
                            $media->originalStorageKey(),
                            $media->checksum(),
                            ImageVariantRegistry::names(),
                        ),
                    );
                }
                $documents = $f->get('supportsCommerciaux')->getData();
                foreach (is_array($documents) ? $documents : [] as $file) {
                    if (!($file instanceof UploadedFile)) {
                        continue;
                    }
                    $asset = $this->documentUploader->upload(
                        $file,
                        $a->fiche(),
                        DocumentUsage::CommercialSupport,
                    );
                    $uploadedDocuments[] = $asset;
                    $em->persist($asset);
                    $resource = new RessourceLieu();
                    $resource->configureDocument(
                        DocumentUsage::CommercialSupport,
                    );
                    $resource->changeDamAssetId($asset->id());
                    $title = $f->get('supportTitle')->getData();
                    $resource->changeLegende(
                        is_string($title) && '' !== trim($title)
                            ? $title
                            : pathinfo(
                                $file->getClientOriginalName(),
                                PATHINFO_FILENAME,
                            ),
                    );
                    $source = $f->get('supportSource')->getData();
                    $resource->changeSource(
                        is_string($source) ? $source : null,
                    );
                    if (true === $f->get('supportRightsGranted')->getData()) {
                        $resource->grantRights($this->actor());
                    }
                    $a->addRessource($resource);
                }
                $currentMediaIds = array_map(
                    static fn (
                        RessourceLieu $resource,
                    ): string => $resource->damAssetId(),
                    array_filter(
                        $a->ressources()->toArray(),
                        static fn (
                            RessourceLieu $resource,
                        ): bool => NatureRessource::Photo ===
                            $resource->nature(),
                    ),
                );
                foreach (
                    array_diff($existingMediaIds, $currentMediaIds) as $removed
                ) {
                    if ('' !== $removed) {
                        $o->enqueue(new DeleteMedia($removed));
                    }
                }
                $em->persist($a);
                $o->enqueue(new IndexFiche($a->fiche()->idString()));
                $em->flush();
            } catch (\DomainException $exception) {
                $this->cleanupUploads($uploaded);
                $this->cleanupDocuments($uploadedDocuments);
                $f->get('ressources')->addError(
                    new FormError($exception->getMessage()),
                );

                return $this->formResponse($f, $a, $creation);
            } catch (\Throwable $exception) {
                $this->cleanupUploads($uploaded);
                $this->cleanupDocuments($uploadedDocuments);
                throw $exception;
            }
            $this->addFlash(
                'success',
                $creation ? 'Activité créée.' : 'Activité modifiée.',
            );

            return $this->redirectToRoute('app_pim_activite_show', [
                'id' => $a->id(),
            ]);
        }

        return $this->formResponse($f, $a, $creation);
    }

    /** @param list<MediaAsset> $assets */
    private function cleanupUploads(array $assets): void
    {
        foreach ($assets as $asset) {
            try {
                $this->imageUploader->delete($asset);
            } catch (\Throwable) {
            }
        }
    }

    /** @param list<MediaAsset> $assets */
    private function cleanupDocuments(array $assets): void
    {
        foreach ($assets as $asset) {
            try {
                $this->documentUploader->delete($asset);
            } catch (\Throwable) {
            }
        }
    }

    /** @param \Symfony\Component\Form\FormInterface<mixed> $form */
    private function formResponse(
        \Symfony\Component\Form\FormInterface $form,
        Activite $a,
        bool $creation,
    ): Response {
        $documents = [];
        if (!$creation) {
            foreach ($a->ressources() as $resource) {
                if (
                    NatureRessource::Document !== $resource->nature()
                    || DocumentUsage::CommercialSupport !==
                        $resource->documentUsage()
                ) {
                    continue;
                }
                $documents[] = [
                    'resource' => $resource,
                    'metadata_form' => $this->forms
                        ->createNamed(
                            'activite_document_metadata_'.$resource->id(),
                            ActiviteDocumentMetadataType::class,
                            [
                                'title' => $resource->legende(),
                                'source' => $resource->source(),
                                'rightsGranted' => $resource->rightsGranted(),
                            ],
                            [
                                'action' => $this->generateUrl(
                                    'app_pim_activite_document_update',
                                    [
                                        'id' => $a->id(),
                                        'resourceId' => $resource->id(),
                                    ],
                                ),
                                'method' => 'POST',
                            ],
                        )
                        ->createView(),
                    'replace_form' => $this->forms
                        ->createNamed(
                            'activite_document_replace_'.$resource->id(),
                            LieuDocumentReplaceType::class,
                            null,
                            [
                                'action' => $this->generateUrl(
                                    'app_pim_activite_document_replace',
                                    [
                                        'id' => $a->id(),
                                        'resourceId' => $resource->id(),
                                    ],
                                ),
                                'method' => 'POST',
                            ],
                        )
                        ->createView(),
                    'publication_form' => $this->forms
                        ->createNamed(
                            'activite_document_publication_'.$resource->id(),
                            ActionType::class,
                            null,
                            [
                                'action' => $this->generateUrl(
                                    'app_pim_activite_document_publication',
                                    [
                                        'id' => $a->id(),
                                        'resourceId' => $resource->id(),
                                    ],
                                ),
                                'button_label' => 'published' ===
                                    $resource->publicationStatus()?->value
                                        ? 'Dépublier'
                                        : 'Publier',
                                'csrf_token_id' => 'activite-document-publication-'.
                                    $resource->id(),
                            ],
                        )
                        ->createView(),
                    'delete_form' => $this->forms
                        ->createNamed(
                            'activite_document_delete_'.$resource->id(),
                            ActionType::class,
                            null,
                            [
                                'action' => $this->generateUrl(
                                    'app_pim_activite_document_delete',
                                    [
                                        'id' => $a->id(),
                                        'resourceId' => $resource->id(),
                                    ],
                                ),
                                'button_label' => 'Supprimer',
                                'csrf_token_id' => 'activite-document-delete-'.
                                    $resource->id(),
                            ],
                        )
                        ->createView(),
                ];
            }
        }

        return $this->render('pim/activite/form.html.twig', [
            'form' => $form,
            'activite' => $a,
            'creation' => $creation,
            'documents' => $documents,
        ]);
    }

    private function transition(
        Request $r,
        Activite $a,
        string $name,
        EntityManagerInterface $em,
        OutboxPublisherInterface $o,
        callable $change,
        string $permission,
    ): Response {
        $this->denyAccessUnlessGranted($permission, $a->fiche());
        $f = $this->action($a, $name, ucfirst($name));
        $f->handleRequest($r);
        if ($f->isSubmitted() && $f->isValid()) {
            $change($a, $this->actor());
            $o->enqueue(new IndexFiche($a->fiche()->idString()));
            $em->flush();
        }

        return $this->redirectToRoute('app_pim_activite_show', [
            'id' => $a->id(),
        ]);
    }

    private function require(string $id, ActiviteRepository $r): Activite
    {
        $a = $r->find($id);
        if (!($a instanceof Activite)) {
            throw $this->createNotFoundException('Activité introuvable.');
        }

        return $a;
    }

    /** @return \Symfony\Component\Form\FormInterface<mixed> */
    private function action(
        Activite $a,
        string $name,
        string $label,
    ): \Symfony\Component\Form\FormInterface {
        return $this->forms->createNamed(
            $name.'_activite',
            ActionType::class,
            null,
            [
                'action' => $this->generateUrl('app_pim_activite_'.$name, [
                    'id' => $a->id(),
                ]),
                'button_label' => $label,
                'csrf_token_id' => $name.'-activite-'.$a->id(),
            ],
        );
    }

    /** @return \Symfony\Component\Form\FormInterface<mixed> */
    private function rejectForm(
        Activite $a,
    ): \Symfony\Component\Form\FormInterface {
        return $this->forms
            ->createNamedBuilder('reject_activite')
            ->setAction(
                $this->generateUrl('app_pim_activite_reject', [
                    'id' => $a->id(),
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
        $u = $this->getUser();
        if (!($u instanceof User)) {
            throw $this->createAccessDeniedException();
        }

        return $u->id();
    }
}
