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
use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Enum\NatureRessource;
use App\Pim\Enum\StatutFiche;
use App\Pim\Enum\TypeFiche;
use App\Pim\Form\ActiviteDocumentMetadataType;
use App\Pim\Form\ServiceSearchType;
use App\Pim\Form\ServiceEvenementielType;
use App\Pim\Form\LieuDocumentReplaceType;
use App\Pim\Message\IndexFiche;
use App\Pim\ReadModel\FicheCursor;
use App\Pim\Repository\ServiceEvenementielRepository;
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

#[Route("/admin/services", name: "app_pim_service_")]
final class ServiceEvenementielController extends AbstractController
{
    public function __construct(
        private readonly FormFactoryInterface $forms,
        private readonly FicheImageUploader $imageUploader,
        private readonly FicheDocumentUploader $documentUploader,
    ) {}

    #[Route("", name: "index", methods: ["GET"])]
    public function index(
        Request $request,
        ServiceEvenementielRepository $repo,
        SearchEngineInterface $search,
    ): Response {
        $form = $this->createForm(
            ServiceSearchType::class,
            [
                "q" => $request->query->getString("q"),
                "status" => StatutFiche::tryFrom(
                    $request->query->getString("status"),
                ),
                "limit" => max(
                    1,
                    min(100, $request->query->getInt("limit", 50)),
                ),
            ],
            ["action" => $this->generateUrl("app_pim_service_index")],
        );
        $form->handleRequest($request);
        if ($form->isSubmitted() && !$form->isValid()) {
            throw new BadRequestHttpException("Critères invalides.");
        }
        $data = $form->getData();
        $status = $data["status"] ?? null;
        $text = trim((string) ($data["q"] ?? ""));
        $limit = max(1, min(100, (int) ($data["limit"] ?? 50)));
        $cursor = $request->query->getString("cursor") ?: null;
        try {
            if ("" !== $text) {
                $filters = ["type" => TypeFiche::ServiceEvenementiel->value];
                if ($status instanceof StatutFiche) {
                    $filters["status"] = $status->value;
                }
                $page = $search->search(
                    new SearchQuery($text, $filters, $limit, $cursor),
                );
                $items = $repo->findListItemsByIds(
                    array_map(static fn($r): string => $r->id, $page->results),
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

        return $this->render("pim/service/index.html.twig", [
            "services" => $items,
            "next_cursor" => $page->nextCursor,
            "status" => $status,
            "query" => $text,
            "result_count" => $count,
            "search_form" => $form->createView(),
            "pending_count" => $repo->countByStatus(
                StatutFiche::EnAttenteValidation,
            ),
        ]);
    }

    #[Route("/nouveau", name: "new", methods: ["GET", "POST"])]
    public function new(
        Request $r,
        EntityManagerInterface $em,
        OutboxPublisherInterface $o,
    ): Response {
        return $this->save($r, new ServiceEvenementiel(), $em, $o, true);
    }

    #[
        Route(
            "/{id}",
            name: "show",
            requirements: ["id" => "[0-9A-HJKMNP-TV-Z]{26}"],
            methods: ["GET"],
        ),
    ]
    public function show(
        string $id,
        ServiceEvenementielRepository $repo,
    ): Response {
        $service = $this->require($id, $repo);
        $this->denyAccessUnlessGranted(FicheVoter::VIEW, $service->fiche());

        return $this->render("pim/service/show.html.twig", [
            "service" => $service,
            "delete_form" => $this->action(
                $service,
                "delete",
                "Supprimer",
            )->createView(),
            "submit_form" => $this->action(
                $service,
                "submit",
                "Soumettre à validation",
            )->createView(),
            "validate_form" => $this->action(
                $service,
                "validate",
                "Valider et publier",
            )->createView(),
            "archive_form" => $this->action(
                $service,
                "archive",
                "Archiver",
            )->createView(),
            "reject_form" => $this->rejectForm($service)->createView(),
        ]);
    }

    #[
        Route(
            "/{id}/modifier",
            name: "edit",
            requirements: ["id" => "[0-9A-HJKMNP-TV-Z]{26}"],
            methods: ["GET", "POST"],
        ),
    ]
    public function edit(
        Request $r,
        string $id,
        ServiceEvenementielRepository $repo,
        EntityManagerInterface $em,
        OutboxPublisherInterface $o,
    ): Response {
        $service = $this->require($id, $repo);
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $service->fiche());

        return $this->save($r, $service, $em, $o, false);
    }

    #[
        Route(
            "/{id}/supprimer",
            name: "delete",
            requirements: ["id" => "[0-9A-HJKMNP-TV-Z]{26}"],
            methods: ["POST"],
        ),
    ]
    public function delete(
        Request $r,
        string $id,
        ServiceEvenementielRepository $repo,
        EntityManagerInterface $em,
    ): Response {
        $service = $this->require($id, $repo);
        $this->denyAccessUnlessGranted(FicheVoter::DELETE, $service->fiche());
        $f = $this->action($service, "delete", "Supprimer");
        $f->handleRequest($r);
        if ($f->isSubmitted() && $f->isValid()) {
            $em->remove($service);
            $em->flush();
            $this->addFlash("success", "Service supprimé.");
        }

        return $this->redirectToRoute("app_pim_service_index");
    }

    #[
        Route(
            "/{id}/soumettre",
            name: "submit",
            requirements: ["id" => "[0-9A-HJKMNP-TV-Z]{26}"],
            methods: ["POST"],
        ),
    ]
    public function submit(
        Request $r,
        string $id,
        ServiceEvenementielRepository $repo,
        EntityManagerInterface $em,
        OutboxPublisherInterface $o,
        ValidatorInterface $v,
    ): Response {
        $service = $this->require($id, $repo);
        $this->denyAccessUnlessGranted(FicheVoter::SUBMIT, $service->fiche());
        $f = $this->action($service, "submit", "Soumettre");
        $f->handleRequest($r);
        if ($f->isSubmitted() && $f->isValid()) {
            $errors = $v->validate($service, null, [
                ValidationGroups::DRAFT,
                ValidationGroups::SUBMISSION,
            ]);
            if (count($errors)) {
                foreach ($errors as $error) {
                    $this->addFlash(
                        "error",
                        $error->getPropertyPath() .
                            " : " .
                            $error->getMessage(),
                    );
                }

                return $this->redirectToRoute("app_pim_service_edit", [
                    "id" => $service->id(),
                ]);
            }
            $service->fiche()->submitForValidation($this->actor());
            $o->enqueue(new IndexFiche($service->fiche()->idString()));
            $em->flush();
        }

        return $this->redirectToRoute("app_pim_service_show", [
            "id" => $service->id(),
        ]);
    }

    #[
        Route(
            "/{id}/valider",
            name: "validate",
            requirements: ["id" => "[0-9A-HJKMNP-TV-Z]{26}"],
            methods: ["POST"],
        ),
    ]
    public function validateService(
        Request $r,
        string $id,
        ServiceEvenementielRepository $repo,
        EntityManagerInterface $em,
        OutboxPublisherInterface $o,
    ): Response {
        return $this->transition(
            $r,
            $this->require($id, $repo),
            "validate",
            $em,
            $o,
            static fn(ServiceEvenementiel $service, string $actor) => $service
                ->fiche()
                ->validateAndPublish($actor),
            FicheVoter::VALIDATE,
        );
    }

    #[
        Route(
            "/{id}/archiver",
            name: "archive",
            requirements: ["id" => "[0-9A-HJKMNP-TV-Z]{26}"],
            methods: ["POST"],
        ),
    ]
    public function archive(
        Request $r,
        string $id,
        ServiceEvenementielRepository $repo,
        EntityManagerInterface $em,
        OutboxPublisherInterface $o,
    ): Response {
        return $this->transition(
            $r,
            $this->require($id, $repo),
            "archive",
            $em,
            $o,
            static fn(ServiceEvenementiel $service, string $actor) => $service
                ->fiche()
                ->archive($actor),
            FicheVoter::ARCHIVE,
        );
    }

    #[
        Route(
            "/{id}/refuser",
            name: "reject",
            requirements: ["id" => "[0-9A-HJKMNP-TV-Z]{26}"],
            methods: ["POST"],
        ),
    ]
    public function reject(
        Request $r,
        string $id,
        ServiceEvenementielRepository $repo,
        EntityManagerInterface $em,
        OutboxPublisherInterface $o,
    ): Response {
        $service = $this->require($id, $repo);
        $this->denyAccessUnlessGranted(FicheVoter::VALIDATE, $service->fiche());
        $f = $this->rejectForm($service);
        $f->handleRequest($r);
        if ($f->isSubmitted() && $f->isValid()) {
            $service
                ->fiche()
                ->rejectValidation(
                    $this->actor(),
                    (string) $f->get("reason")->getData(),
                );
            $o->enqueue(new IndexFiche($service->fiche()->idString()));
            $em->flush();
        }

        return $this->redirectToRoute("app_pim_service_show", [
            "id" => $service->id(),
        ]);
    }

    private function save(
        Request $r,
        ServiceEvenementiel $service,
        EntityManagerInterface $em,
        OutboxPublisherInterface $o,
        bool $creation,
    ): Response {
        $existingMediaIds = array_map(
            static fn(
                RessourceLieu $resource,
            ): string => $resource->damAssetId(),
            array_filter(
                $service->ressources()->toArray(),
                static fn(
                    RessourceLieu $resource,
                ): bool => NatureRessource::Photo === $resource->nature(),
            ),
        );
        $f = $this->createForm(ServiceEvenementielType::class, $service);
        $f->handleRequest($r);
        if ($f->isSubmitted() && $f->isValid()) {
            $uploaded = [];
            $uploadedDocuments = [];
            try {
                foreach ($f->get("ressources") as $resourceForm) {
                    $file = $resourceForm->get("image")->getData();
                    $resource = $resourceForm->getData();
                    if (
                        !($file instanceof UploadedFile) ||
                        !($resource instanceof RessourceLieu)
                    ) {
                        continue;
                    }
                    $media = $this->imageUploader->upload(
                        $file,
                        $service->fiche(),
                    );
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
                $documents = $f->get("supportsCommerciaux")->getData();
                foreach (is_array($documents) ? $documents : [] as $file) {
                    if (!($file instanceof UploadedFile)) {
                        continue;
                    }
                    $asset = $this->documentUploader->upload(
                        $file,
                        $service->fiche(),
                        DocumentUsage::CommercialSupport,
                    );
                    $uploadedDocuments[] = $asset;
                    $em->persist($asset);
                    $resource = new RessourceLieu();
                    $resource->configureDocument(
                        DocumentUsage::CommercialSupport,
                    );
                    $resource->changeDamAssetId($asset->id());
                    $title = $f->get("supportTitle")->getData();
                    $resource->changeLegende(
                        is_string($title) && "" !== trim($title)
                            ? $title
                            : pathinfo(
                                $file->getClientOriginalName(),
                                PATHINFO_FILENAME,
                            ),
                    );
                    $source = $f->get("supportSource")->getData();
                    $resource->changeSource(
                        is_string($source) ? $source : null,
                    );
                    if (true === $f->get("supportRightsGranted")->getData()) {
                        $resource->grantRights($this->actor());
                    }
                    $service->addRessource($resource);
                }
                $currentMediaIds = array_map(
                    static fn(
                        RessourceLieu $resource,
                    ): string => $resource->damAssetId(),
                    array_filter(
                        $service->ressources()->toArray(),
                        static fn(
                            RessourceLieu $resource,
                        ): bool => NatureRessource::Photo ===
                            $resource->nature(),
                    ),
                );
                foreach (
                    array_diff($existingMediaIds, $currentMediaIds)
                    as $removed
                ) {
                    if ("" !== $removed) {
                        $o->enqueue(new DeleteMedia($removed));
                    }
                }
                $em->persist($service);
                $o->enqueue(new IndexFiche($service->fiche()->idString()));
                $em->flush();
            } catch (\DomainException $exception) {
                $this->cleanupUploads($uploaded);
                $this->cleanupDocuments($uploadedDocuments);
                $f->get("ressources")->addError(
                    new FormError($exception->getMessage()),
                );

                return $this->formResponse($f, $service, $creation);
            } catch (\Throwable $exception) {
                $this->cleanupUploads($uploaded);
                $this->cleanupDocuments($uploadedDocuments);
                throw $exception;
            }
            $this->addFlash(
                "success",
                $creation ? "Service créé." : "Service modifié.",
            );

            return $this->redirectToRoute("app_pim_service_show", [
                "id" => $service->id(),
            ]);
        }

        return $this->formResponse($f, $service, $creation);
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
        ServiceEvenementiel $service,
        bool $creation,
    ): Response {
        $documents = [];
        if (!$creation) {
            foreach ($service->ressources() as $resource) {
                if (
                    NatureRessource::Document !== $resource->nature() ||
                    DocumentUsage::CommercialSupport !==
                        $resource->documentUsage()
                ) {
                    continue;
                }
                $documents[] = [
                    "resource" => $resource,
                    "metadata_form" => $this->forms
                        ->createNamed(
                            "service_document_metadata_" . $resource->id(),
                            ActiviteDocumentMetadataType::class,
                            [
                                "title" => $resource->legende(),
                                "source" => $resource->source(),
                                "rightsGranted" => $resource->rightsGranted(),
                            ],
                            [
                                "action" => $this->generateUrl(
                                    "app_pim_service_document_update",
                                    [
                                        "id" => $service->id(),
                                        "resourceId" => $resource->id(),
                                    ],
                                ),
                                "method" => "POST",
                            ],
                        )
                        ->createView(),
                    "replace_form" => $this->forms
                        ->createNamed(
                            "service_document_replace_" . $resource->id(),
                            LieuDocumentReplaceType::class,
                            null,
                            [
                                "action" => $this->generateUrl(
                                    "app_pim_service_document_replace",
                                    [
                                        "id" => $service->id(),
                                        "resourceId" => $resource->id(),
                                    ],
                                ),
                                "method" => "POST",
                            ],
                        )
                        ->createView(),
                    "publication_form" => $this->forms
                        ->createNamed(
                            "service_document_publication_" . $resource->id(),
                            ActionType::class,
                            null,
                            [
                                "action" => $this->generateUrl(
                                    "app_pim_service_document_publication",
                                    [
                                        "id" => $service->id(),
                                        "resourceId" => $resource->id(),
                                    ],
                                ),
                                "button_label" =>
                                    "published" ===
                                    $resource->publicationStatus()?->value
                                        ? "Dépublier"
                                        : "Publier",
                                "csrf_token_id" =>
                                    "service-document-publication-" .
                                    $resource->id(),
                            ],
                        )
                        ->createView(),
                    "delete_form" => $this->forms
                        ->createNamed(
                            "service_document_delete_" . $resource->id(),
                            ActionType::class,
                            null,
                            [
                                "action" => $this->generateUrl(
                                    "app_pim_service_document_delete",
                                    [
                                        "id" => $service->id(),
                                        "resourceId" => $resource->id(),
                                    ],
                                ),
                                "button_label" => "Supprimer",
                                "csrf_token_id" =>
                                    "service-document-delete-" .
                                    $resource->id(),
                            ],
                        )
                        ->createView(),
                ];
            }
        }

        return $this->render("pim/service/form.html.twig", [
            "form" => $form,
            "service" => $service,
            "creation" => $creation,
            "documents" => $documents,
        ]);
    }

    private function transition(
        Request $r,
        ServiceEvenementiel $service,
        string $name,
        EntityManagerInterface $em,
        OutboxPublisherInterface $o,
        callable $change,
        string $permission,
    ): Response {
        $this->denyAccessUnlessGranted($permission, $service->fiche());
        $f = $this->action($service, $name, ucfirst($name));
        $f->handleRequest($r);
        if ($f->isSubmitted() && $f->isValid()) {
            $change($service, $this->actor());
            $o->enqueue(new IndexFiche($service->fiche()->idString()));
            $em->flush();
        }

        return $this->redirectToRoute("app_pim_service_show", [
            "id" => $service->id(),
        ]);
    }

    private function require(
        string $id,
        ServiceEvenementielRepository $r,
    ): ServiceEvenementiel {
        $service = $r->find($id);
        if (!($service instanceof ServiceEvenementiel)) {
            throw $this->createNotFoundException("Service introuvable.");
        }

        return $service;
    }

    /** @return \Symfony\Component\Form\FormInterface<mixed> */
    private function action(
        ServiceEvenementiel $service,
        string $name,
        string $label,
    ): \Symfony\Component\Form\FormInterface {
        return $this->forms->createNamed(
            $name . "_service",
            ActionType::class,
            null,
            [
                "action" => $this->generateUrl("app_pim_service_" . $name, [
                    "id" => $service->id(),
                ]),
                "button_label" => $label,
                "csrf_token_id" => $name . "-service-" . $service->id(),
            ],
        );
    }

    /** @return \Symfony\Component\Form\FormInterface<mixed> */
    private function rejectForm(
        ServiceEvenementiel $service,
    ): \Symfony\Component\Form\FormInterface {
        return $this->forms
            ->createNamedBuilder("reject_service")
            ->setAction(
                $this->generateUrl("app_pim_service_reject", [
                    "id" => $service->id(),
                ]),
            )
            ->setMethod("POST")
            ->add("reason", TextareaType::class, [
                "label" => "Motif du refus",
                "required" => true,
            ])
            ->add("submit", SubmitType::class, ["label" => "Refuser"])
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
