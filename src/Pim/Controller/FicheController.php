<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Account\Entity\User;
use App\Pim\Enum\StatutFiche;
use App\Pim\Enum\TypeFiche;
use App\Pim\Form\FicheBrowseType;
use App\Pim\Form\FicheCreation;
use App\Pim\Form\FicheCreationType;
use App\Pim\ReadModel\FicheCursor;
use App\Pim\ReadModel\FicheListResult;
use App\Pim\ReadModel\GlobalSearchItem;
use App\Pim\Repository\FicheRepository;
use App\Pim\Repository\LocalisationRepository;
use App\Pim\Service\FicheCreationManager;
use App\Pim\Service\FicheDuplicateDetector;
use App\Pim\Service\FicheRouteResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\SubmitButton;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/fiches', name: 'app_pim_fiche_')]
final class FicheController extends AbstractController
{
    #[Route('/nouvelle', name: 'new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        FicheCreationManager $manager,
        FicheDuplicateDetector $duplicateDetector,
        FicheRouteResolver $routes,
    ): Response {
        $creation = new FicheCreation();
        $form = $this->createForm(FicheCreationType::class, $creation);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getUser();
            if (!$user instanceof User) {
                throw $this->createAccessDeniedException();
            }
            $entreprise = $manager->lookupEntreprise($creation);
            $duplicates = $duplicateDetector->detect($creation, $entreprise);
            $confirmButton = $form->get('creerQuandMeme');
            $confirmed = $confirmButton instanceof SubmitButton && $confirmButton->isClicked();
            if ([] !== $duplicates && !$confirmed) {
                // 422 : Turbo Drive ignore une réponse 200 à un POST de formulaire.
                return $this->render('pim/fiche/new.html.twig', [
                    'form' => $form->createView(),
                    'duplicates' => $duplicates,
                ], new Response(null, Response::HTTP_UNPROCESSABLE_ENTITY));
            }
            try {
                $result = $manager->create($creation, $user, $entreprise);
                $this->addFlash('success', 'Fiche créée. Complétez maintenant les informations détaillées.');
                if (null !== $result->entreprise) {
                    $this->addFlash('success', sprintf(
                        'Fiche pré-remplie depuis l’annuaire des entreprises (%s%s).',
                        $result->entreprise->denomination ?? 'entreprise trouvée',
                        null !== $result->entreprise->siret ? ', SIRET '.$result->entreprise->siret : '',
                    ));
                } else {
                    $this->addFlash('warning', 'Aucune entreprise correspondante dans l’annuaire : la fiche a été créée sans pré-remplissage.');
                }

                return $this->redirect($routes->editUrl($result->fiche->type(), $result->fiche->idString()));
            } catch (\DomainException $exception) {
                $form->addError(new FormError($exception->getMessage()));
            }
        }

        return $this->render(
            'pim/fiche/new.html.twig',
            ['form' => $form->createView(), 'duplicates' => []],
            new Response(null, $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK),
        );
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        FicheRepository $repository,
        LocalisationRepository $localisations,
        FicheRouteResolver $routes,
    ): Response {
        $form = $this->createForm(FicheBrowseType::class, [
            'type' => TypeFiche::tryFrom($request->query->getString('type')),
            'status' => StatutFiche::tryFrom($request->query->getString('status')),
            'country' => $request->query->getString('country') ?: null,
            'completeness_min' => $request->query->filter(
                'completeness_min',
                null,
                \FILTER_VALIDATE_INT,
                ['flags' => \FILTER_NULL_ON_FAILURE],
            ),
            'completeness_max' => $request->query->filter(
                'completeness_max',
                null,
                \FILTER_VALIDATE_INT,
                ['flags' => \FILTER_NULL_ON_FAILURE],
            ),
            'limit' => $request->query->filter(
                'limit',
                50,
                \FILTER_VALIDATE_INT,
                ['flags' => \FILTER_NULL_ON_FAILURE],
            ) ?? 50,
        ], [
            'action' => $this->generateUrl('app_pim_fiche_index'),
            'countries' => $localisations->findDistinctCountryCodes(),
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && !$form->isValid()) {
            throw new BadRequestHttpException('Critères de filtrage invalides.');
        }

        /** @var array{type?: TypeFiche|null, status?: StatutFiche|null, country?: string|null, completeness_min?: int|null, completeness_max?: int|null, limit?: int|null} $criteria */
        $criteria = $form->getData();
        $completenessMin = $criteria['completeness_min'] ?? null;
        $completenessMax = $criteria['completeness_max'] ?? null;
        if (null !== $completenessMin && null !== $completenessMax && $completenessMin > $completenessMax) {
            throw new BadRequestHttpException('Complétude invalide.');
        }
        $limit = max(1, min(100, (int) ($criteria['limit'] ?? 50)));
        try {
            $page = $repository->findAllListPage(
                FicheCursor::decode($request->query->getString('cursor') ?: null),
                $limit,
                $criteria['type'] ?? null,
                $criteria['status'] ?? null,
                $criteria['country'] ?? null,
                $completenessMin,
                $completenessMax,
            );
        } catch (\InvalidArgumentException $exception) {
            throw new BadRequestHttpException($exception->getMessage(), $exception);
        }

        $results = array_map(static function (GlobalSearchItem $item) use ($routes): FicheListResult {
            if (TypeFiche::Traiteur === $item->type) {
                return new FicheListResult($item, null, null);
            }

            return new FicheListResult(
                $item,
                $routes->showUrl($item->type, $item->id),
                $routes->editUrl($item->type, $item->id),
            );
        }, $page->items);

        return $this->render('pim/fiche/index.html.twig', [
            'results' => $results,
            'next_cursor' => $page->nextCursor,
            'search_form' => $form->createView(),
            'type' => $criteria['type'] ?? null,
            'status' => $criteria['status'] ?? null,
            'country' => $criteria['country'] ?? null,
            'completeness_min' => $completenessMin,
            'completeness_max' => $completenessMax,
            'limit' => $limit,
            'total_count' => $repository->countAll(),
        ]);
    }
}
