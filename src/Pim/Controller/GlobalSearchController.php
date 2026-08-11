<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Pim\Enum\StatutFiche;
use App\Pim\Enum\TypeFiche;
use App\Pim\Form\GlobalSearchType;
use App\Pim\Repository\LocalisationRepository;
use App\Pim\Service\GlobalSearchProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_BP_EDITOR')]
final class GlobalSearchController extends AbstractController
{
    #[Route('/recherche', name: 'app_pim_global_search', methods: ['GET'])]
    public function __invoke(
        Request $request,
        GlobalSearchProvider $provider,
        LocalisationRepository $localisations,
    ): Response {
        $form = $this->createForm(GlobalSearchType::class, [
            'q' => $request->query->getString('q'),
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
            'action' => $this->generateUrl('app_pim_global_search'),
            'countries' => $localisations->findDistinctCountryCodes(),
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && !$form->isValid()) {
            throw new BadRequestHttpException('Critères de recherche invalides.');
        }

        /** @var array{q?: string|null, type?: TypeFiche|null, status?: StatutFiche|null, country?: string|null, completeness_min?: int|null, completeness_max?: int|null, limit?: int|null} $criteria */
        $criteria = $form->getData();
        $cursor = $request->query->getString('cursor') ?: null;
        try {
            $page = $provider->search(
                (string) ($criteria['q'] ?? ''),
                $criteria['type'] ?? null,
                $criteria['status'] ?? null,
                (int) ($criteria['limit'] ?? 50),
                $cursor,
                $criteria['country'] ?? null,
                $criteria['completeness_min'] ?? null,
                $criteria['completeness_max'] ?? null,
            );
        } catch (\InvalidArgumentException $exception) {
            throw new BadRequestHttpException($exception->getMessage(), $exception);
        }

        return $this->render('pim/search/index.html.twig', [
            'search_form' => $form->createView(),
            'page' => $page,
            'query' => trim((string) ($criteria['q'] ?? '')),
            'type' => $criteria['type'] ?? null,
            'status' => $criteria['status'] ?? null,
            'country' => $criteria['country'] ?? null,
            'completeness_min' => $criteria['completeness_min'] ?? null,
            'completeness_max' => $criteria['completeness_max'] ?? null,
            'limit' => (int) ($criteria['limit'] ?? 50),
        ]);
    }
}
