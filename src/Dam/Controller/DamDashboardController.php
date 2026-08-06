<?php

declare(strict_types=1);

namespace App\Dam\Controller;

use App\Account\Service\CurrentActorProvider;
use App\Dam\Service\DamDashboardProvider;
use App\Dam\Service\DamResourceManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/dam', name: 'app_dam_')]
final class DamDashboardController extends AbstractController
{
    #[Route('', name: 'dashboard', methods: ['GET'])]
    public function index(Request $request, DamDashboardProvider $provider): Response
    {
        return $this->render('dam/dashboard.html.twig', $provider->page(
            $request->query->getString('filter', DamDashboardProvider::FILTER_DUPLICATES),
            '' === $request->query->getString('type') ? null : $request->query->getString('type'),
            $request->query->getInt('page', 1),
        ));
    }

    #[Route('/doublons/{id}/accepter', name: 'duplicate_accept', methods: ['POST'])]
    public function acceptDuplicate(string $id, Request $request, DamResourceManager $manager, CurrentActorProvider $actor): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('dam-duplicate-accept-'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        $manager->acceptDuplicate($id, $actor->id());
        $this->addFlash('success', 'Le doublon est accepté et ne sera plus signalé.');

        return $this->redirectToRoute('app_dam_dashboard', array_filter([
            'filter' => DamDashboardProvider::FILTER_DUPLICATES,
            'type' => $request->query->getString('type') ?: null,
            'page' => max(1, $request->query->getInt('page', 1)),
        ]));
    }

    #[Route('/doublons/{id}/supprimer', name: 'duplicate_delete', methods: ['POST'])]
    public function deleteDuplicate(string $id, Request $request, DamResourceManager $manager, CurrentActorProvider $actor): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('dam-duplicate-delete-'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        $manager->deleteDuplicate($id, $actor->id());
        $this->addFlash('success', "L'image en doublon a été retirée et sa suppression DAM a été planifiée.");

        return $this->redirectToRoute('app_dam_dashboard', array_filter([
            'filter' => DamDashboardProvider::FILTER_DUPLICATES,
            'type' => $request->query->getString('type') ?: null,
            'page' => max(1, $request->query->getInt('page', 1)),
        ]));
    }

    #[Route('/ressources/{id}/droits/{action}', name: 'rights', requirements: ['action' => 'grant|revoke'], methods: ['POST'])]
    public function rights(string $id, string $action, Request $request, DamResourceManager $manager, CurrentActorProvider $actor): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('dam-rights-'.$action.'-'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        $manager->changeRights($id, 'grant' === $action, $actor->id());
        $this->addFlash('success', 'Les droits du média ont été mis à jour.');

        return $this->redirectToRoute('app_dam_dashboard', array_filter([
            'filter' => $request->query->getString('filter', DamDashboardProvider::FILTER_RIGHTS_EXPIRING),
            'type' => $request->query->getString('type') ?: null,
            'page' => max(1, $request->query->getInt('page', 1)),
        ]));
    }

    #[Route('/medias/{id}/relancer', name: 'retry', methods: ['POST'])]
    public function retry(string $id, Request $request, DamResourceManager $manager): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('dam-retry-'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        $manager->retry($id);
        $this->addFlash('success', 'Le retraitement du média a été planifié.');

        return $this->redirectToRoute('app_dam_dashboard', array_filter([
            'filter' => DamDashboardProvider::FILTER_FAILED,
            'type' => $request->query->getString('type') ?: null,
            'page' => max(1, $request->query->getInt('page', 1)),
        ]));
    }
}
