<?php

declare(strict_types=1);

namespace App\Dashboard\Controller;

use App\Dashboard\Message\ComputeDashboardStats;
use App\Dashboard\Service\DashboardPageProvider;
use App\Shared\Form\ActionType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/tableau-de-bord', name: 'app_dashboard_')]
final class DashboardController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(DashboardPageProvider $provider): Response
    {
        $recalculateForm = $this->container->get('form.factory')->createNamed('', ActionType::class, null, [
            'action' => $this->generateUrl('app_dashboard_recalculate'),
            'button_label' => 'Recalculer maintenant',
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'dashboard-recalculate',
        ]);

        return $this->render('dashboard/index.html.twig', $provider->page() + [
            'recalculate_form' => $recalculateForm->createView(),
        ]);
    }

    #[Route('/recalculer', name: 'recalculate', methods: ['POST'])]
    public function recalculate(Request $request, MessageBusInterface $bus): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('dashboard-recalculate', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        $bus->dispatch(new ComputeDashboardStats());
        $this->addFlash('success', 'Le recalcul des statistiques a été planifié. Rechargez la page dans quelques instants.');

        return $this->redirectToRoute('app_dashboard_index');
    }
}
