<?php

declare(strict_types=1);

namespace App\Audit\Controller;

use App\Account\Security\FicheVoter;
use App\Audit\Entity\AuditChange;
use App\Audit\Entity\AuditRevision;
use App\Audit\Form\RestoreFormFactory;
use App\Audit\Repository\AuditChangeRepository;
use App\Audit\Repository\AuditRevisionRepository;
use App\Audit\Restore\AuditRestorer;
use App\Audit\Restore\NotRestorableException;
use App\Audit\Restore\RestorePreviewBuilder;
use App\Audit\Restore\StaleVersionException;
use App\Pim\Entity\Fiche;
use App\Pim\Repository\FicheRepository;
use App\Pim\Service\FicheRouteResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RestoreController extends AbstractController
{
    #[
        Route(
            '/admin/historique/changes/{changeId}/restaurer',
            name: 'app_audit_restore_change',
            requirements: ['changeId' => '[0-9A-HJKMNP-TV-Z]{26}'],
            methods: ['POST'],
        ),
    ]
    public function restoreChange(
        string $changeId,
        Request $request,
        AuditChangeRepository $changes,
        FicheRepository $fiches,
        RestoreFormFactory $formFactory,
        AuditRestorer $restorer,
        FicheRouteResolver $routes,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_BP_VALIDATOR');
        $change = $changes->find($changeId);
        if (!$change instanceof AuditChange) {
            throw $this->createNotFoundException('Modification introuvable.');
        }
        $fiche = $fiches->find($change->revision()->ficheId());
        if (!$fiche instanceof Fiche) {
            throw $this->createNotFoundException('Fiche introuvable.');
        }
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $fiche);
        $historyUrl = $routes->historyUrl($fiche->type(), $fiche->idString());
        $form = $formFactory->restoreChange($changeId, $fiche->version());
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'Restauration refusée : formulaire invalide.');

            return $this->redirect($historyUrl);
        }
        $request->attributes->set('_audit_source', 'restoration');
        $request->attributes->set('_audit_action', 'restore');
        $request->attributes->set(
            '_audit_correlation_id',
            'restore:'.$change->revision()->id(),
        );
        try {
            $restorer->restoreChange(
                $change,
                (int) $form->get('expectedVersion')->getData(),
            );
            $this->addFlash('success', sprintf(
                'Champ « %s » restauré.',
                $change->path(),
            ));
        } catch (StaleVersionException|NotRestorableException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirect($historyUrl);
    }

    #[
        Route(
            '/admin/historique/revisions/{revisionId}/restaurer',
            name: 'app_audit_restore_revision_preview',
            requirements: ['revisionId' => '[0-9A-HJKMNP-TV-Z]{26}'],
            methods: ['GET'],
        ),
    ]
    public function previewRevision(
        string $revisionId,
        AuditRevisionRepository $revisions,
        FicheRepository $fiches,
        RestoreFormFactory $formFactory,
        RestorePreviewBuilder $previewBuilder,
        FicheRouteResolver $routes,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_BP_VALIDATOR');
        $revision = $revisions->find($revisionId);
        if (!$revision instanceof AuditRevision) {
            throw $this->createNotFoundException('Révision introuvable.');
        }
        $fiche = $fiches->find($revision->ficheId());
        if (!$fiche instanceof Fiche) {
            throw $this->createNotFoundException('Fiche introuvable.');
        }
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $fiche);
        $rows = $previewBuilder->preview($revision, $fiche);
        $restorable = RestorePreviewBuilder::restorableCount($rows);

        return $this->render('audit/restore_preview.html.twig', [
            'revision' => $revision,
            'fiche' => $fiche,
            'rows' => $rows,
            'restorable_count' => $restorable,
            'history_url' => $routes->historyUrl(
                $fiche->type(),
                $fiche->idString(),
            ),
            'restore_form' => $restorable > 0
                ? $formFactory
                    ->restoreRevision($revision->id(), $fiche->version())
                    ->createView()
                : null,
        ]);
    }

    #[
        Route(
            '/admin/historique/revisions/{revisionId}/restaurer',
            name: 'app_audit_restore_revision',
            requirements: ['revisionId' => '[0-9A-HJKMNP-TV-Z]{26}'],
            methods: ['POST'],
        ),
    ]
    public function restoreRevision(
        string $revisionId,
        Request $request,
        AuditRevisionRepository $revisions,
        FicheRepository $fiches,
        RestoreFormFactory $formFactory,
        AuditRestorer $restorer,
        FicheRouteResolver $routes,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_BP_VALIDATOR');
        $revision = $revisions->find($revisionId);
        if (!$revision instanceof AuditRevision) {
            throw $this->createNotFoundException('Révision introuvable.');
        }
        $fiche = $fiches->find($revision->ficheId());
        if (!$fiche instanceof Fiche) {
            throw $this->createNotFoundException('Fiche introuvable.');
        }
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $fiche);
        $historyUrl = $routes->historyUrl($fiche->type(), $fiche->idString());
        $form = $formFactory->restoreRevision($revisionId, $fiche->version());
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'Restauration refusée : formulaire invalide.');

            return $this->redirect($historyUrl);
        }
        $request->attributes->set('_audit_source', 'restoration');
        $request->attributes->set('_audit_action', 'restore');
        $request->attributes->set(
            '_audit_correlation_id',
            'restore:'.$revision->id(),
        );
        try {
            $applied = $restorer->restoreRevision(
                $revision,
                (int) $form->get('expectedVersion')->getData(),
            );
            $this->addFlash('success', sprintf(
                '%d champ(s) restauré(s) : %s.',
                count($applied),
                implode(', ', $applied),
            ));
        } catch (StaleVersionException|NotRestorableException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirect($historyUrl);
    }
}
