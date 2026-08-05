<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Account\Security\FicheVoter;
use App\Account\Service\CurrentActorProvider;
use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Pim\Enum\StatutFiche;
use App\Pim\Enum\TypeFiche;
use App\Pim\Form\FicheActionFormFactory;
use App\Pim\Form\ServiceSearchType;
use App\Pim\Form\ServiceEvenementielType;
use App\Pim\ReadModel\FicheCursor;
use App\Pim\Repository\ServiceEvenementielRepository;
use App\Pim\Service\FicheCountProvider;
use App\Pim\Service\InternalFicheMutationPolicy;
use App\Pim\Service\ServiceEvenementielAdminManager;
use App\Pim\Service\ServiceEvenementielAdminViewBuilder;
use App\Pim\Service\FicheWorkflowManager;
use App\Shared\Search\SearchQuery;
use App\Shared\Service\SearchEngineInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route("/admin/services", name: "app_pim_service_")]
final class ServiceEvenementielController extends AbstractController
{
    #[Route("", name: "index", methods: ["GET"])]
    public function index(
        Request $request,
        ServiceEvenementielRepository $repo,
        SearchEngineInterface $search,
        FicheCountProvider $counts,
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
            "total_count" => $counts->totalByType(
                TypeFiche::ServiceEvenementiel,
            ),
            "pending_count" => $counts->countByStatus(
                TypeFiche::ServiceEvenementiel,
                StatutFiche::EnAttenteValidation,
            ),
        ]);
    }

    #[Route("/nouveau", name: "new", methods: ["GET", "POST"])]
    public function new(
        Request $request,
        ServiceEvenementielAdminManager $manager,
        ServiceEvenementielAdminViewBuilder $view,
        CurrentActorProvider $actor,
    ): Response {
        $service = new ServiceEvenementiel();
        $form = $this->createForm(ServiceEvenementielType::class, $service);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $manager->save($service, $form, [], $actor->id());
                $this->addFlash('success', 'Service créé.');

                return $this->redirectToRoute('app_pim_service_show', ['id' => $service->id()]);
            } catch (\DomainException $exception) {
                $form->get('ressources')->addError(new FormError($exception->getMessage()));
            }
        }

        return $this->render('pim/service/form.html.twig', $view->form($form, $service, true));
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
        FicheActionFormFactory $forms,
    ): Response {
        $service = $repo->find($id);
        if (!$service instanceof ServiceEvenementiel) { throw $this->createNotFoundException('Service introuvable.'); }
        $this->denyAccessUnlessGranted(FicheVoter::VIEW, $service->fiche());

        return $this->render("pim/service/show.html.twig", [
            "service" => $service,
            "delete_form" => $forms->action('service', $service->id(), 'delete', 'Supprimer')->createView(),
            "submit_form" => $forms->action('service', $service->id(), 'submit', 'Soumettre à validation')->createView(),
            "validate_form" => $forms->action('service', $service->id(), 'validate', 'Valider et publier')->createView(),
            "archive_form" => $forms->action('service', $service->id(), 'archive', 'Archiver')->createView(),
            "reject_form" => $forms->reject('service', $service->id())->createView(),
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
        Request $request,
        string $id,
        ServiceEvenementielRepository $repo,
        ServiceEvenementielAdminManager $manager,
        ServiceEvenementielAdminViewBuilder $view,
        CurrentActorProvider $actor,
        InternalFicheMutationPolicy $mutationPolicy,
    ): Response {
        $service = $repo->find($id);
        if (!$service instanceof ServiceEvenementiel) { throw $this->createNotFoundException('Service introuvable.'); }
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $service->fiche());
        $existing = $manager->photoAssetIds($service);
        $form = $this->createForm(ServiceEvenementielType::class, $service);
        $response = $mutationPolicy->execute(
            $service->fiche(),
            function () use ($request, $form, $manager, $service, $existing, $actor): ?Response {
                $form->handleRequest($request);
                if ($form->isSubmitted() && $form->isValid()) {
                    try {
                        $manager->save($service, $form, $existing, $actor->id());
                        $this->addFlash('success', 'Service modifié.');

                        return $this->redirectToRoute('app_pim_service_show', ['id' => $service->id()]);
                    } catch (\DomainException $exception) {
                        $form->get('ressources')->addError(new FormError($exception->getMessage()));
                    }
                }

                return null;
            }
        );
        if ($response instanceof Response) {
            return $response;
        }

        return $this->render('pim/service/form.html.twig', $view->form($form, $service, false));
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
        Request $request,
        string $id,
        ServiceEvenementielRepository $repo,
        FicheActionFormFactory $forms,
        FicheWorkflowManager $workflow,
    ): Response {
        $service = $repo->find($id);
        if (!$service instanceof ServiceEvenementiel) { throw $this->createNotFoundException('Service introuvable.'); }
        $this->denyAccessUnlessGranted(FicheVoter::DELETE, $service->fiche());
        $f = $forms->action('service', $service->id(), 'delete', 'Supprimer');
        $f->handleRequest($request);
        if ($f->isSubmitted() && $f->isValid()) {
            $workflow->delete($service);
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
        Request $request,
        string $id,
        ServiceEvenementielRepository $repo,
        FicheActionFormFactory $forms,
        FicheWorkflowManager $workflow,
        CurrentActorProvider $actor,
    ): Response {
        $service = $repo->find($id);
        if (!$service instanceof ServiceEvenementiel) { throw $this->createNotFoundException('Service introuvable.'); }
        $this->denyAccessUnlessGranted(FicheVoter::SUBMIT, $service->fiche());
        $f = $forms->action('service', $service->id(), 'submit', 'Soumettre');
        $f->handleRequest($request);
        if ($f->isSubmitted() && $f->isValid()) {
            $errors = $workflow->submit($service, $service->fiche(), $actor->id());
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
        Request $request,
        string $id,
        ServiceEvenementielRepository $repo,
        FicheActionFormFactory $forms,
        FicheWorkflowManager $workflow,
        CurrentActorProvider $actor,
    ): Response {
        $service = $repo->find($id);
        if (!$service instanceof ServiceEvenementiel) { throw $this->createNotFoundException('Service introuvable.'); }
        $this->denyAccessUnlessGranted(FicheVoter::VALIDATE, $service->fiche());
        $form = $forms->action('service', $service->id(), 'validate', 'Validate');
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) { $workflow->validate($service->fiche(), $actor->id()); }

        return $this->redirectToRoute('app_pim_service_show', ['id' => $service->id()]);
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
        Request $request,
        string $id,
        ServiceEvenementielRepository $repo,
        FicheActionFormFactory $forms,
        FicheWorkflowManager $workflow,
        CurrentActorProvider $actor,
    ): Response {
        $service = $repo->find($id);
        if (!$service instanceof ServiceEvenementiel) { throw $this->createNotFoundException('Service introuvable.'); }
        $this->denyAccessUnlessGranted(FicheVoter::ARCHIVE, $service->fiche());
        $form = $forms->action('service', $service->id(), 'archive', 'Archive');
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) { $workflow->archive($service->fiche(), $actor->id()); }

        return $this->redirectToRoute('app_pim_service_show', ['id' => $service->id()]);
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
        Request $request,
        string $id,
        ServiceEvenementielRepository $repo,
        FicheActionFormFactory $forms,
        FicheWorkflowManager $workflow,
        CurrentActorProvider $actor,
    ): Response {
        $service = $repo->find($id);
        if (!$service instanceof ServiceEvenementiel) { throw $this->createNotFoundException('Service introuvable.'); }
        $this->denyAccessUnlessGranted(FicheVoter::VALIDATE, $service->fiche());
        $f = $forms->reject('service', $service->id());
        $f->handleRequest($request);
        if ($f->isSubmitted() && $f->isValid()) {
            $workflow->reject($service->fiche(), $actor->id(), (string) $f->get('reason')->getData());
        }

        return $this->redirectToRoute("app_pim_service_show", [
            "id" => $service->id(),
        ]);
    }

}
