<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Account\Security\FicheVoter;
use App\Account\Service\CurrentActorProvider;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Enum\StatutFiche;
use App\Pim\Enum\TypeFiche;
use App\Pim\Form\RestaurantSearchType;
use App\Pim\Form\RestaurantType;
use App\Pim\Form\FicheActionFormFactory;
use App\Pim\ReadModel\FicheCursor;
use App\Pim\Repository\RestaurantRepository;
use App\Pim\Service\FicheCountProvider;
use App\Pim\Service\RestaurantAdminManager;
use App\Pim\Service\RestaurantAdminViewBuilder;
use App\Pim\Service\FicheWorkflowManager;
use App\Shared\Search\SearchQuery;
use App\Shared\Service\SearchEngineInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/restaurants', name: 'app_pim_restaurant_')]
final class RestaurantController extends AbstractController
{
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
        RestaurantAdminManager $manager,
        RestaurantAdminViewBuilder $view,
        CurrentActorProvider $actor,
    ): Response {
        $restaurant = new Restaurant();
        $form = $this->createForm(RestaurantType::class, $restaurant);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $manager->save($restaurant, $form, [], $actor->id());
                $this->addFlash('success', 'Restaurant créé.');

                return $this->redirectToRoute('app_pim_restaurant_show', ['id' => $restaurant->id()]);
            } catch (\DomainException $exception) {
                $form->addError(new FormError($exception->getMessage()));
            }
        }

        return $this->render('pim/restaurant/form.html.twig', $view->form($form, $restaurant, true));
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], methods: ['GET'])]
    public function show(string $id, RestaurantRepository $repository, FicheActionFormFactory $forms): Response
    {
        $restaurant = $repository->find($id);
        if (!$restaurant instanceof Restaurant) { throw $this->createNotFoundException('Restaurant introuvable.'); }
        $this->denyAccessUnlessGranted(FicheVoter::VIEW, $restaurant->fiche());

        return $this->render('pim/restaurant/show.html.twig', [
            'restaurant' => $restaurant,
            'delete_form' => $forms->action('restaurant', $restaurant->id(), 'delete', 'Supprimer')->createView(),
            'submit_form' => $forms->action('restaurant', $restaurant->id(), 'submit', 'Soumettre à validation')->createView(),
            'validate_form' => $forms->action('restaurant', $restaurant->id(), 'validate', 'Valider et publier')->createView(),
            'archive_form' => $forms->action('restaurant', $restaurant->id(), 'archive', 'Archiver')->createView(),
            'reject_form' => $forms->reject('restaurant', $restaurant->id())->createView(),
        ]);
    }

    #[Route('/{id}/modifier', name: 'edit', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        string $id,
        RestaurantRepository $repository,
        RestaurantAdminManager $manager,
        RestaurantAdminViewBuilder $view,
        CurrentActorProvider $actor,
    ): Response {
        $restaurant = $repository->find($id);
        if (!$restaurant instanceof Restaurant) { throw $this->createNotFoundException('Restaurant introuvable.'); }
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $restaurant->fiche());
        $existing = $manager->photoAssetIds($restaurant);
        $form = $this->createForm(RestaurantType::class, $restaurant);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $manager->save($restaurant, $form, $existing, $actor->id());
                $this->addFlash('success', 'Restaurant modifié.');

                return $this->redirectToRoute('app_pim_restaurant_show', ['id' => $restaurant->id()]);
            } catch (\DomainException $exception) {
                $form->addError(new FormError($exception->getMessage()));
            }
        }

        return $this->render('pim/restaurant/form.html.twig', $view->form($form, $restaurant, false));
    }

    #[Route('/{id}/supprimer', name: 'delete', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], methods: ['POST'])]
    public function delete(
        Request $request,
        string $id,
        RestaurantRepository $repository,
        FicheActionFormFactory $forms,
        FicheWorkflowManager $workflow,
    ): Response {
        $restaurant = $repository->find($id);
        if (!$restaurant instanceof Restaurant) { throw $this->createNotFoundException('Restaurant introuvable.'); }
        $this->denyAccessUnlessGranted(FicheVoter::DELETE, $restaurant->fiche());
        $form = $forms->action('restaurant', $restaurant->id(), 'delete', 'Supprimer');
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $workflow->delete($restaurant);
            $this->addFlash('success', 'Restaurant supprimé.');
        }

        return $this->redirectToRoute('app_pim_restaurant_index');
    }

    #[Route('/{id}/soumettre', name: 'submit', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], methods: ['POST'])]
    public function submit(
        Request $request,
        string $id,
        RestaurantRepository $repository,
        FicheActionFormFactory $forms,
        FicheWorkflowManager $workflow,
        CurrentActorProvider $actor,
    ): Response {
        $restaurant = $repository->find($id);
        if (!$restaurant instanceof Restaurant) { throw $this->createNotFoundException('Restaurant introuvable.'); }
        $this->denyAccessUnlessGranted(FicheVoter::SUBMIT, $restaurant->fiche());
        $form = $forms->action('restaurant', $restaurant->id(), 'submit', 'Soumettre');
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $errors = $workflow->submit($restaurant, $restaurant->fiche(), $actor->id());
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
        FicheActionFormFactory $forms,
        FicheWorkflowManager $workflow,
        CurrentActorProvider $actor,
    ): Response {
        $restaurant = $repository->find($id);
        if (!$restaurant instanceof Restaurant) { throw $this->createNotFoundException('Restaurant introuvable.'); }
        $this->denyAccessUnlessGranted(FicheVoter::VALIDATE, $restaurant->fiche());
        $form = $forms->action('restaurant', $restaurant->id(), 'validate', 'Validate');
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) { $workflow->validate($restaurant->fiche(), $actor->id()); }

        return $this->redirectToRoute('app_pim_restaurant_show', ['id' => $restaurant->id()]);
    }

    #[Route('/{id}/archiver', name: 'archive', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], methods: ['POST'])]
    public function archive(
        Request $request,
        string $id,
        RestaurantRepository $repository,
        FicheActionFormFactory $forms,
        FicheWorkflowManager $workflow,
        CurrentActorProvider $actor,
    ): Response {
        $restaurant = $repository->find($id);
        if (!$restaurant instanceof Restaurant) { throw $this->createNotFoundException('Restaurant introuvable.'); }
        $this->denyAccessUnlessGranted(FicheVoter::ARCHIVE, $restaurant->fiche());
        $form = $forms->action('restaurant', $restaurant->id(), 'archive', 'Archive');
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) { $workflow->archive($restaurant->fiche(), $actor->id()); }

        return $this->redirectToRoute('app_pim_restaurant_show', ['id' => $restaurant->id()]);
    }

    #[Route('/{id}/refuser', name: 'reject', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], methods: ['POST'])]
    public function reject(
        Request $request,
        string $id,
        RestaurantRepository $repository,
        FicheActionFormFactory $forms,
        FicheWorkflowManager $workflow,
        CurrentActorProvider $actor,
    ): Response {
        $restaurant = $repository->find($id);
        if (!$restaurant instanceof Restaurant) { throw $this->createNotFoundException('Restaurant introuvable.'); }
        $this->denyAccessUnlessGranted(FicheVoter::VALIDATE, $restaurant->fiche());
        $form = $forms->reject('restaurant', $restaurant->id());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $workflow->reject($restaurant->fiche(), $actor->id(), (string) $form->get('reason')->getData());
        }

        return $this->redirectToRoute('app_pim_restaurant_show', [
            'id' => $restaurant->id(),
        ]);
    }

}
