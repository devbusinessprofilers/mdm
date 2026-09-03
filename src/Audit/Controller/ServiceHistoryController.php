<?php

declare(strict_types=1);

namespace App\Audit\Controller;

use App\Audit\Form\AuditHistoryFilterType;
use App\Audit\Form\RestoreFormFactory;
use App\Audit\Repository\AuditRevisionRepository;
use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Pim\Repository\ServiceEvenementielRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ServiceHistoryController extends AbstractController
{
    #[Route(
        '/referentiel/services/fiche/{id}/historique',
        name: 'app_pim_service_history',
        requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
        methods: ['GET'],
    ),]
    public function __invoke(
        string $id,
        Request $request,
        ServiceEvenementielRepository $services,
        AuditRevisionRepository $revisions,
        RestoreFormFactory $restoreForms,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_BP_VALIDATOR');
        $service = $services->find($id);
        if (!$service instanceof ServiceEvenementiel) {
            throw $this->createNotFoundException('Service introuvable.');
        }
        $form = $this->createForm(AuditHistoryFilterType::class, null, [
            'method' => 'GET',
        ]);
        $form->handleRequest($request);
        $filters =
            $form->isSubmitted() && $form->isValid() ? $form->getData() : [];
        $page = $revisions->history(
            $service->fiche()->idString(),
            $request->query->getString('cursor') ?: null,
            30,
            is_array($filters) ? $filters : [],
        );
        $next = count($page) > 30 ? $page[29]->id() : null;
        $page = array_slice($page, 0, 30);

        return $this->render('audit/service_history.html.twig', [
            'service' => $service,
            'revisions' => $page,
            'restore_forms' => $restoreForms->changeFormViews(
                $page,
                $service->fiche()->version(),
            ),
            'next_cursor' => $next,
            'filter_form' => $form->createView(),
        ]);
    }
}
