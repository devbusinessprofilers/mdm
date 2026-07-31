<?php

declare(strict_types=1);

namespace App\Audit\Controller;

use App\Audit\Form\AuditHistoryFilterType;
use App\Audit\Repository\AuditRevisionRepository;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Repository\RestaurantRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RestaurantHistoryController extends AbstractController
{
    #[Route(
        '/admin/restaurants/{id}/historique',
        name: 'app_pim_restaurant_history',
        requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
        methods: ['GET'],
    )]
    public function __invoke(
        string $id,
        Request $request,
        RestaurantRepository $restaurants,
        AuditRevisionRepository $revisions,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_BP_VALIDATOR');
        $restaurant = $restaurants->find($id);
        if (!$restaurant instanceof Restaurant) {
            throw $this->createNotFoundException('Restaurant introuvable.');
        }

        $form = $this->createForm(AuditHistoryFilterType::class, null, [
            'method' => 'GET',
        ]);
        $form->handleRequest($request);
        $filters = $form->isSubmitted() && $form->isValid()
            ? $form->getData()
            : [];
        $page = $revisions->history(
            $restaurant->fiche()->idString(),
            $request->query->getString('cursor') ?: null,
            30,
            is_array($filters) ? $filters : [],
        );
        $next = count($page) > 30 ? $page[29]->id() : null;

        return $this->render('audit/restaurant_history.html.twig', [
            'restaurant' => $restaurant,
            'revisions' => array_slice($page, 0, 30),
            'next_cursor' => $next,
            'filter_form' => $form->createView(),
        ]);
    }
}
