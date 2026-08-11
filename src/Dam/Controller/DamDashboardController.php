<?php

declare(strict_types=1);

namespace App\Dam\Controller;

use App\Account\Service\CurrentActorProvider;
use App\Dam\Service\DamActionReturnUrl;
use App\Dam\Service\DamDashboardProvider;
use App\Dam\Service\DamResourceManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * La supervision (index) vit sous /admin ; les actions de curation restent
 * hors /admin car elles sont aussi déclenchées depuis /medias par les
 * éditeurs et validateurs (partial dam/_dashboard_contenu.html.twig).
 */
#[Route(name: 'app_dam_')]
final class DamDashboardController extends AbstractController
{
    #[Route('/admin/dam', name: 'dashboard', methods: ['GET'])]
    public function index(Request $request, DamDashboardProvider $provider): Response
    {
        return $this->render('dam/dashboard.html.twig', $provider->page(
            $request->query->getString('filter', DamDashboardProvider::FILTER_DUPLICATES),
            '' === $request->query->getString('type') ? null : $request->query->getString('type'),
            $request->query->getInt('page', 1),
        ));
    }

    #[Route('/dam/doublons/{id}/accepter', name: 'duplicate_accept', methods: ['POST'])]
    #[IsGranted('ROLE_BP_EDITOR')]
    public function acceptDuplicate(string $id, Request $request, DamResourceManager $manager, CurrentActorProvider $actor, DamActionReturnUrl $retour): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('dam-duplicate-accept-'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        $manager->acceptDuplicate($id, $actor->id());
        $this->addFlash('success', 'Le doublon est accepté et ne sera plus signalé.');

        return $this->redirect($retour->compute($request, DamDashboardProvider::FILTER_DUPLICATES));
    }

    #[Route('/dam/doublons/{id}/supprimer', name: 'duplicate_delete', methods: ['POST'])]
    #[IsGranted('ROLE_BP_EDITOR')]
    public function deleteDuplicate(string $id, Request $request, DamResourceManager $manager, CurrentActorProvider $actor, DamActionReturnUrl $retour): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('dam-duplicate-delete-'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        $manager->deleteDuplicate($id, $actor->id());
        $this->addFlash('success', "L'image en doublon a été retirée et sa suppression DAM a été planifiée.");

        return $this->redirect($retour->compute($request, DamDashboardProvider::FILTER_DUPLICATES));
    }

    #[Route('/dam/ressources/{id}/droits/{action}', name: 'rights', requirements: ['action' => 'grant|revoke'], methods: ['POST'])]
    #[IsGranted('ROLE_BP_VALIDATOR')]
    public function rights(string $id, string $action, Request $request, DamResourceManager $manager, CurrentActorProvider $actor, DamActionReturnUrl $retour): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('dam-rights-'.$action.'-'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        $manager->changeRights($id, 'grant' === $action, $actor->id());
        $this->addFlash('success', 'Les droits du média ont été mis à jour.');

        return $this->redirect($retour->compute($request, $request->query->getString('filter', DamDashboardProvider::FILTER_RIGHTS_EXPIRING)));
    }

    #[Route('/dam/medias/{id}/relancer', name: 'retry', methods: ['POST'])]
    #[IsGranted('ROLE_BP_EDITOR')]
    public function retry(string $id, Request $request, DamResourceManager $manager, DamActionReturnUrl $retour): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('dam-retry-'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        $manager->retry($id);
        $this->addFlash('success', 'Le retraitement du média a été planifié.');

        return $this->redirect($retour->compute($request, DamDashboardProvider::FILTER_FAILED));
    }
}
