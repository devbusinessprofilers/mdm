<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Pim\Enum\StatutFiche;
use App\Pim\Enum\TypeFiche;
use App\Pim\Form\GlobalSearchType;
use App\Pim\Service\GlobalSearchProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class GlobalSearchController extends AbstractController
{
    #[Route('/admin/recherche', name: 'app_pim_global_search', methods: ['GET'])]
    public function __invoke(Request $request, GlobalSearchProvider $provider): Response
    {
        $form = $this->createForm(GlobalSearchType::class, [
            'q' => $request->query->getString('q'),
            'type' => TypeFiche::tryFrom($request->query->getString('type')),
            'status' => StatutFiche::tryFrom($request->query->getString('status')),
            'limit' => $request->query->filter(
                'limit',
                50,
                \FILTER_VALIDATE_INT,
                ['flags' => \FILTER_NULL_ON_FAILURE],
            ) ?? 50,
        ], ['action' => $this->generateUrl('app_pim_global_search')]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && !$form->isValid()) {
            throw new BadRequestHttpException('Critères de recherche invalides.');
        }

        /** @var array{q?: string|null, type?: TypeFiche|null, status?: StatutFiche|null, limit?: int|null} $criteria */
        $criteria = $form->getData();
        $cursor = $request->query->getString('cursor') ?: null;
        try {
            $page = $provider->search(
                (string) ($criteria['q'] ?? ''),
                $criteria['type'] ?? null,
                $criteria['status'] ?? null,
                (int) ($criteria['limit'] ?? 50),
                $cursor,
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
            'limit' => (int) ($criteria['limit'] ?? 50),
        ]);
    }
}
