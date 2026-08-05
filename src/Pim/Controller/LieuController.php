<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Account\Security\FicheVoter;
use App\Account\Service\CurrentActorProvider;
use App\Dam\Service\LieuPhotoPresenter;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Enum\StatutFiche;
use App\Pim\Enum\TypeFiche;
use App\Pim\Form\FicheActionFormFactory;
use App\Pim\Form\LieuSearchType;
use App\Pim\Form\LieuType;
use App\Pim\ReadModel\FicheCursor;
use App\Pim\Repository\LieuRepository;
use App\Pim\Service\FicheCountProvider;
use App\Pim\Service\InternalFicheMutationPolicy;
use App\Pim\Service\LieuAdminManager;
use App\Pim\Service\LieuAdminViewBuilder;
use App\Pim\Service\FicheWorkflowManager;
use App\Shared\Search\SearchQuery;
use App\Shared\Service\SearchEngineInterface;
use League\Flysystem\FilesystemException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/lieux', name: 'app_pim_lieu_')]
final class LieuController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        LieuRepository $repository,
        SearchEngineInterface $searchEngine,
        FicheCountProvider $counts,
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
            'total_count' => $counts->totalByType(TypeFiche::Lieu),
            'pending_count' => $counts->countByStatus(
                TypeFiche::Lieu,
                StatutFiche::EnAttenteValidation,
            ),
        ]);
    }

    #[Route('/nouveau', name: 'new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        LieuAdminManager $manager,
        LieuAdminViewBuilder $view,
    ): Response {
        $lieu = new Lieu();
        $form = $this->createForm(LieuType::class, $lieu);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $manager->save($lieu, $form, []);
                $this->addFlash('success', 'Lieu créé.');

                return $this->redirectToRoute('app_pim_lieu_show', ['id' => $lieu->id()]);
            } catch (\DomainException $exception) {
                $form->get('ressources')->addError(new FormError($exception->getMessage()));
            } catch (FilesystemException) {
                $form->get('ressources')->addError(new FormError('Le stockage des médias est temporairement indisponible. Aucun fichier n’a été enregistré.'));
            }
        }

        return $this->render('pim/lieu/form.html.twig', $view->form($form, $lieu, true));
    }

    #[
        Route(
            '/{id}',
            name: 'show',
            requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
            methods: ['GET'],
        ),
    ]
    public function show(
        string $id,
        LieuRepository $repository,
        LieuPhotoPresenter $photos,
        FicheActionFormFactory $forms,
    ): Response
    {
        $lieu = $repository->find($id);
        if (!$lieu instanceof Lieu) { $this->addFlash('warning', 'Ce lieu n’existe plus ou vient d’être supprimé.'); return $this->redirectToRoute('app_pim_lieu_index'); }
        $this->denyAccessUnlessGranted(FicheVoter::VIEW, $lieu->fiche());

        return $this->render('pim/lieu/show.html.twig', [
            'lieu' => $lieu,
            'photos' => $photos->photos($lieu),
            'delete_form' => $forms->action('lieu', $lieu->id(), 'delete', 'Supprimer', true)->createView(),
            'submit_form' => $forms->action('lieu', $lieu->id(), 'submit', 'Soumettre à validation')->createView(),
            'validate_form' => $forms->action('lieu', $lieu->id(), 'validate', 'Valider et publier')->createView(),
            'archive_form' => $forms->action('lieu', $lieu->id(), 'archive', 'Archiver')->createView(),
            'reject_form' => $forms->reject('lieu', $lieu->id())->createView(),
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
        LieuAdminManager $manager,
        LieuAdminViewBuilder $view,
        InternalFicheMutationPolicy $mutationPolicy,
    ): Response {
        $lieu = $repository->find($id);
        if (!$lieu instanceof Lieu) { $this->addFlash('warning', 'Ce lieu n’existe plus ou vient d’être supprimé.'); return $this->redirectToRoute('app_pim_lieu_index'); }
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $lieu->fiche());
        $existing = $manager->photoAssetIds($lieu);
        $form = $this->createForm(LieuType::class, $lieu);
        $response = $mutationPolicy->execute(
            $lieu->fiche(),
            function () use ($request, $form, $manager, $lieu, $existing): ?Response {
                $form->handleRequest($request);
                if ($form->isSubmitted() && $form->isValid()) {
                    try {
                        $manager->save($lieu, $form, $existing);
                        $this->addFlash('success', 'Lieu modifié.');

                        return $this->redirectToRoute('app_pim_lieu_show', ['id' => $lieu->id()]);
                    } catch (\DomainException $exception) {
                        $form->get('ressources')->addError(new FormError($exception->getMessage()));
                    } catch (FilesystemException) {
                        $form->get('ressources')->addError(new FormError('Le stockage des médias est temporairement indisponible. Aucun fichier n’a été enregistré.'));
                    }
                }

                return null;
            }
        );
        if ($response instanceof Response) {
            return $response;
        }

        return $this->render('pim/lieu/form.html.twig', $view->form($form, $lieu, false));
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
        FicheActionFormFactory $forms,
        LieuAdminManager $manager,
    ): Response {
        $lieu = $repository->find($id);
        if (!$lieu instanceof Lieu) { $this->addFlash('warning', 'Ce lieu n’existe plus ou vient d’être supprimé.'); return $this->redirectToRoute('app_pim_lieu_index'); }
        $this->denyAccessUnlessGranted(FicheVoter::DELETE, $lieu->fiche());

        $form = $forms->action('lieu', $lieu->id(), 'delete', 'Supprimer', true);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $manager->delete($lieu);
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
        FicheActionFormFactory $forms,
        FicheWorkflowManager $workflow,
        CurrentActorProvider $actor,
    ): Response {
        $lieu = $repository->find($id);
        if (!$lieu instanceof Lieu) { throw $this->createNotFoundException('Lieu introuvable.'); }
        $this->denyAccessUnlessGranted(FicheVoter::SUBMIT, $lieu->fiche());
        $form = $forms->action('lieu', $lieu->id(), 'submit', 'Soumettre à validation');
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $violations = $workflow->submit($lieu, $lieu->fiche(), $actor->id());
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
        FicheActionFormFactory $forms,
        FicheWorkflowManager $workflow,
        CurrentActorProvider $actor,
    ): Response {
        $lieu = $repository->find($id);
        if (!$lieu instanceof Lieu) { throw $this->createNotFoundException('Lieu introuvable.'); }
        $this->denyAccessUnlessGranted(FicheVoter::VALIDATE, $lieu->fiche());
        $form = $forms->action('lieu', $lieu->id(), 'validate', 'Valider et publier');
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $workflow->validate($lieu->fiche(), $actor->id());
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
        FicheActionFormFactory $forms,
        FicheWorkflowManager $workflow,
        CurrentActorProvider $actor,
    ): Response {
        $lieu = $repository->find($id);
        if (!$lieu instanceof Lieu) { throw $this->createNotFoundException('Lieu introuvable.'); }
        $this->denyAccessUnlessGranted(FicheVoter::VALIDATE, $lieu->fiche());
        $form = $forms->reject('lieu', $lieu->id());
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{reason: string} $data */
            $data = $form->getData();
            $workflow->reject($lieu->fiche(), $actor->id(), $data['reason']);
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
        FicheActionFormFactory $forms,
        LieuAdminManager $manager,
        CurrentActorProvider $actor,
    ): Response {
        $lieu = $repository->find($id);
        if (!$lieu instanceof Lieu) { throw $this->createNotFoundException('Lieu introuvable.'); }
        $this->denyAccessUnlessGranted(FicheVoter::ARCHIVE, $lieu->fiche());
        $form = $forms->action('lieu', $lieu->id(), 'archive', 'Archiver');
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $manager->archive($lieu, $actor->id());
            $this->addFlash('success', 'Fiche archivée.');
        }

        return $this->redirectToRoute('app_pim_lieu_show', [
            'id' => $lieu->id(),
        ]);
    }
}
