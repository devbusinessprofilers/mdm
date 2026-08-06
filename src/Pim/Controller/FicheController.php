<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Pim\Enum\StatutFiche;
use App\Pim\Enum\TypeFiche;
use App\Pim\Form\FicheBrowseType;
use App\Pim\ReadModel\FicheCursor;
use App\Pim\ReadModel\FicheListResult;
use App\Pim\ReadModel\GlobalSearchItem;
use App\Pim\Repository\FicheRepository;
use App\Pim\Repository\LocalisationRepository;
use App\Pim\Service\FicheRouteResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/fiches', name: 'app_pim_fiche_')]
final class FicheController extends AbstractController
{
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
