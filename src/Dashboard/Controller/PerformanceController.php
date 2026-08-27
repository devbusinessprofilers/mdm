<?php

declare(strict_types=1);

namespace App\Dashboard\Controller;

use App\Dashboard\Form\FailedMessageFormFactory;
use App\Dashboard\Form\LogFiltreType;
use App\Dashboard\Model\LogFilter;
use App\Dashboard\Repository\LogEntryRepository;
use App\Dashboard\Service\FailedMessageActions;
use App\Dashboard\Service\PerformanceDataProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Monitoring type « gestionnaire de tâches » : état des workers (heartbeats),
 * graphiques temporels (débit, files, charge, mémoire), files Messenger, DLQ
 * avec relance, et visionneuse des logs persistés en BDD.
 */
#[Route('/admin/performance', name: 'app_performance_')]
#[IsGranted('ROLE_ADMIN')]
final class PerformanceController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        PerformanceDataProvider $provider,
        LogEntryRepository $logs,
        FormFactoryInterface $formFactory,
        FailedMessageFormFactory $failedForms,
    ): Response {
        $filtre = LogFilter::fromRequest($request);
        $recherche = $logs->recherche($filtre);
        $pages = max(1, (int) ceil($recherche['total'] / LogFilter::PAR_PAGE));

        // Nom vide : les clés d'URL restent plates (niveau, canal, q…),
        // celles que LogFilter::fromRequest lit.
        $formFiltres = $formFactory->createNamed('', LogFiltreType::class, null, [
            'canaux' => $logs->canauxConnus(),
        ]);
        $formFiltres->setData([
            'niveau' => $filtre->niveauMin,
            'canal' => $filtre->canal,
            'q' => '' !== $filtre->q ? $filtre->q : null,
            'depuis' => $filtre->depuis,
            'jusqua' => $filtre->jusqua,
        ]);

        $tableaux = $provider->tableaux();
        $tableaux['failed'] = $failedForms->lignesAvecFormulaires($tableaux['failed']);

        return $this->render('dashboard/performance.html.twig', [
            'donnees' => $provider->data($request->query->getInt('fenetre', 15)),
            'tableaux' => $tableaux,
            'fenetres' => PerformanceDataProvider::FENETRES_MINUTES,
            'logs' => $recherche['items'],
            'logs_total' => $recherche['total'],
            'logs_page' => min($filtre->page, $pages),
            'logs_pages' => $pages,
            'compteurs_niveaux' => $logs->compteursParNiveau(),
            'form_filtres' => $formFiltres,
        ]);
    }

    /** Instantané JSON pour les graphiques et cartes (contrôleur Stimulus `performance`). */
    #[Route('/data', name: 'data', methods: ['GET'])]
    public function data(Request $request, PerformanceDataProvider $provider): JsonResponse
    {
        $response = new JsonResponse($provider->data($request->query->getInt('fenetre', 15)));
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }

    /** Fragment HTML des tableaux (files + DLQ), rechargé par poll_controller. */
    #[Route('/tableaux', name: 'tableaux', methods: ['GET'])]
    public function tableaux(PerformanceDataProvider $provider, FailedMessageFormFactory $failedForms): Response
    {
        $tableaux = $provider->tableaux();
        $tableaux['failed'] = $failedForms->lignesAvecFormulaires($tableaux['failed']);

        $response = $this->render('dashboard/_performance_tableaux.html.twig', [
            'tableaux' => $tableaux,
        ]);
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }

    #[Route('/failed/{id}/reessayer', name: 'failed_reessayer', methods: ['POST'])]
    public function reessayer(string $id, Request $request, FailedMessageFormFactory $failedForms, FailedMessageActions $actions): Response
    {
        $form = $failedForms->action($id, 'reessayer');
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            throw $this->createAccessDeniedException('Action invalide.');
        }
        if ($actions->reessayer($id)) {
            $this->addFlash('success', 'Message renvoyé vers son transport d’origine.');
        } else {
            $this->addFlash('error', 'Message introuvable dans la file d’échec.');
        }

        return $this->redirectToRoute('app_performance_index');
    }

    #[Route('/failed/{id}/supprimer', name: 'failed_supprimer', methods: ['POST'])]
    public function supprimer(string $id, Request $request, FailedMessageFormFactory $failedForms, FailedMessageActions $actions): Response
    {
        $form = $failedForms->action($id, 'supprimer');
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            throw $this->createAccessDeniedException('Action invalide.');
        }
        if ($actions->supprimer($id)) {
            $this->addFlash('success', 'Message supprimé de la file d’échec.');
        } else {
            $this->addFlash('error', 'Message introuvable dans la file d’échec.');
        }

        return $this->redirectToRoute('app_performance_index');
    }
}
