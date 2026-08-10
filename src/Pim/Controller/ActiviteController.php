<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Account\Security\FicheVoter;
use App\Account\Service\CurrentActorProvider;
use App\Pim\Entity\Activite\Activite;
use App\Pim\Enum\StatutFiche;
use App\Pim\Enum\TypeFiche;
use App\Pim\Form\FicheActionFormFactory;
use App\Pim\Form\ActiviteSearchType;
use App\Pim\Form\ActiviteType;
use App\Pim\ReadModel\FicheCursor;
use App\Pim\Repository\ActiviteRepository;
use App\Pim\Service\FicheCountProvider;
use App\Pim\Service\ActiviteAdminManager;
use App\Pim\Service\ActiviteAdminViewBuilder;
use App\Pim\Service\InternalFicheMutationPolicy;
use App\Pim\Service\FicheWorkflowManager;
use App\Shared\Search\SearchQuery;
use App\Shared\Service\SearchEngineInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/activites', name: 'app_pim_activite_')]
final class ActiviteController extends AbstractController
{

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        ActiviteRepository $repo,
        SearchEngineInterface $search,
        FicheCountProvider $counts,
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
            'total_count' => $counts->totalByType(TypeFiche::Activite),
            'pending_count' => $counts->countByStatus(
                TypeFiche::Activite,
                StatutFiche::EnAttenteValidation,
            ),
        ]);
    }

    #[Route('/nouveau', name: 'new', methods: ['GET'])]
    public function new(): Response
    {
        // La création passe désormais par le formulaire unique de création de fiche.
        return $this->redirectToRoute('app_pim_fiche_new');
    }

    #[
        Route(
            '/{id}',
            name: 'show',
            requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
            methods: ['GET'],
        ),
    ]
    public function show(string $id, ActiviteRepository $repo, FicheActionFormFactory $forms): Response
    {
        $a = $repo->find($id);
        if (!$a instanceof Activite) { throw $this->createNotFoundException('Activité introuvable.'); }
        $this->denyAccessUnlessGranted(FicheVoter::VIEW, $a->fiche());

        return $this->render('pim/activite/show.html.twig', [
            'activite' => $a,
            'delete_form' => $forms->action('activite', $a->id(), 'delete', 'Supprimer')->createView(),
            'submit_form' => $forms->action('activite', $a->id(), 'submit', 'Soumettre à validation')->createView(),
            'validate_form' => $forms->action('activite', $a->id(), 'validate', 'Valider et publier')->createView(),
            'archive_form' => $forms->action('activite', $a->id(), 'archive', 'Archiver')->createView(),
            'reject_form' => $forms->reject('activite', $a->id())->createView(),
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
        ActiviteRepository $repo,
        ActiviteAdminManager $manager,
        ActiviteAdminViewBuilder $view,
        CurrentActorProvider $actor,
        InternalFicheMutationPolicy $mutationPolicy,
    ): Response {
        $a = $repo->find($id);
        if (!$a instanceof Activite) { throw $this->createNotFoundException('Activité introuvable.'); }
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $a->fiche());
        $existing = $manager->photoAssetIds($a);
        $form = $this->createForm(ActiviteType::class, $a);
        $response = $mutationPolicy->execute(
            $a->fiche(),
            function () use ($request, $form, $manager, $a, $existing, $actor): ?Response {
                $form->handleRequest($request);
                if ($form->isSubmitted() && $form->isValid()) {
                    try {
                        $manager->save($a, $form, $existing, $actor->id());
                        $this->addFlash('success', 'Activité modifiée.');

                        return $this->redirectToRoute('app_pim_activite_show', ['id' => $a->id()]);
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

        return $this->render('pim/activite/form.html.twig', $view->form($form, $a, false));
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
        ActiviteRepository $repo,
        FicheActionFormFactory $forms,
        FicheWorkflowManager $workflow,
    ): Response {
        $a = $repo->find($id);
        if (!$a instanceof Activite) { throw $this->createNotFoundException('Activité introuvable.'); }
        $this->denyAccessUnlessGranted(FicheVoter::DELETE, $a->fiche());
        $f = $forms->action('activite', $a->id(), 'delete', 'Supprimer');
        $f->handleRequest($request);
        if ($f->isSubmitted() && $f->isValid()) {
            $workflow->delete($a);
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
        Request $request,
        string $id,
        ActiviteRepository $repo,
        FicheActionFormFactory $forms,
        FicheWorkflowManager $workflow,
        CurrentActorProvider $actor,
    ): Response {
        $a = $repo->find($id);
        if (!$a instanceof Activite) { throw $this->createNotFoundException('Activité introuvable.'); }
        $this->denyAccessUnlessGranted(FicheVoter::SUBMIT, $a->fiche());
        $f = $forms->action('activite', $a->id(), 'submit', 'Soumettre');
        $f->handleRequest($request);
        if ($f->isSubmitted() && $f->isValid()) {
            $errors = $workflow->submit($a, $a->fiche(), $actor->id());
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
        Request $request,
        string $id,
        ActiviteRepository $repo,
        FicheActionFormFactory $forms,
        FicheWorkflowManager $workflow,
        CurrentActorProvider $actor,
    ): Response {
        $a = $repo->find($id);
        if (!$a instanceof Activite) { throw $this->createNotFoundException('Activité introuvable.'); }
        $this->denyAccessUnlessGranted(FicheVoter::VALIDATE, $a->fiche());
        $form = $forms->action('activite', $a->id(), 'validate', 'Validate');
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) { $workflow->validate($a->fiche(), $actor->id()); }

        return $this->redirectToRoute('app_pim_activite_show', ['id' => $a->id()]);
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
        ActiviteRepository $repo,
        FicheActionFormFactory $forms,
        FicheWorkflowManager $workflow,
        CurrentActorProvider $actor,
    ): Response {
        $a = $repo->find($id);
        if (!$a instanceof Activite) { throw $this->createNotFoundException('Activité introuvable.'); }
        $this->denyAccessUnlessGranted(FicheVoter::ARCHIVE, $a->fiche());
        $form = $forms->action('activite', $a->id(), 'archive', 'Archive');
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) { $workflow->archive($a->fiche(), $actor->id()); }

        return $this->redirectToRoute('app_pim_activite_show', ['id' => $a->id()]);
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
        ActiviteRepository $repo,
        FicheActionFormFactory $forms,
        FicheWorkflowManager $workflow,
        CurrentActorProvider $actor,
    ): Response {
        $a = $repo->find($id);
        if (!$a instanceof Activite) { throw $this->createNotFoundException('Activité introuvable.'); }
        $this->denyAccessUnlessGranted(FicheVoter::VALIDATE, $a->fiche());
        $f = $forms->reject('activite', $a->id());
        $f->handleRequest($request);
        if ($f->isSubmitted() && $f->isValid()) {
            $workflow->reject($a->fiche(), $actor->id(), (string) $f->get('reason')->getData());
        }

        return $this->redirectToRoute('app_pim_activite_show', [
            'id' => $a->id(),
        ]);
    }

}
