<?php

declare(strict_types=1);

namespace App\Audit\Controller;

use App\Audit\Form\AuditHistoryFilterType;
use App\Audit\Form\RestoreFormFactory;
use App\Audit\Repository\AuditRevisionRepository;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Repository\LieuRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LieuHistoryController extends AbstractController
{
    #[Route(
        '/referentiel/lieux/fiche/{id}/historique',
        name: 'app_pim_lieu_history',
        requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
        methods: ['GET'],
    ),]
    public function __invoke(
        string $id,
        Request $request,
        LieuRepository $lieux,
        AuditRevisionRepository $revisions,
        RestoreFormFactory $restoreForms,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_BP_VALIDATOR');
        $lieu = $lieux->find($id);
        if (!$lieu instanceof Lieu) {
            throw $this->createNotFoundException('Lieu introuvable.');
        }
        $form = $this->createForm(AuditHistoryFilterType::class, null, [
            'method' => 'GET',
        ]);
        $form->handleRequest($request);
        $filters =
            $form->isSubmitted() && $form->isValid() ? $form->getData() : [];
        $page = $revisions->history(
            $lieu->fiche()->idString(),
            $request->query->getString('cursor') ?: null,
            30,
            is_array($filters) ? $filters : [],
        );
        $next = count($page) > 30 ? $page[29]->id() : null;
        $page = array_slice($page, 0, 30);

        return $this->render('audit/lieu_history.html.twig', [
            'lieu' => $lieu,
            'revisions' => $page,
            'restore_forms' => $restoreForms->changeFormViews(
                $page,
                $lieu->fiche()->version(),
            ),
            'next_cursor' => $next,
            'filter_form' => $form->createView(),
        ]);
    }
}
