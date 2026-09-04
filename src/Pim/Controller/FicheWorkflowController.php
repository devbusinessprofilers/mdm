<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Account\Service\CurrentActorProvider;
use App\Pim\Enum\FicheTransition;
use App\Pim\Form\FicheActionFormFactory;
use App\Pim\Service\FicheDetailResolver;
use App\Pim\Service\FicheRouteResolver;
use App\Pim\Service\FicheTransitionExecutor;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Transitions de workflow d'une fiche (soumettre, valider, publier, refuser,
 * archiver, désarchiver, republier, supprimer), toutes gammes. Les 32 routes
 * `app_pim_{domaine}_{transition}` sur `/referentiel/{gamme}/fiche/{id}/{segment}`
 * sont déclarées dans config/routes/pim_workflow.php ; chacune arrive ici
 * avec sa gamme et sa transition en paramètres.
 */
final class FicheWorkflowController extends AbstractController
{
    public function transition(
        Request $request,
        string $gamme,
        string $id,
        string $transition,
        FicheDetailResolver $resolver,
        FicheActionFormFactory $forms,
        FicheTransitionExecutor $executor,
        FicheRouteResolver $routes,
        CurrentActorProvider $actor,
    ): Response {
        $transition = FicheTransition::from($transition);
        $entite = $resolver->parSlugEtId($gamme, $id) ?? throw $this->createNotFoundException('Fiche introuvable.');
        $type = $entite->fiche()->type();
        $this->denyAccessUnlessGranted($transition->droit(), $entite->fiche());

        $form = FicheTransition::Refuser === $transition
            ? $forms->reject($type->domaine(), $id)
            : $forms->action($type->domaine(), $id, $transition->value, $transition->libelle(), FicheTransition::Supprimer === $transition);
        $form->handleRequest($request);
        if ($form->isSubmitted() && !$form->isValid() && FicheTransition::Refuser === $transition) {
            $this->addFlash('warning', 'Le motif du refus est obligatoire.');
        }
        if ($form->isSubmitted() && $form->isValid()) {
            $motif = FicheTransition::Refuser === $transition ? (string) $form->get('reason')->getData() : null;
            foreach ($executor->executer($transition, $entite, $actor->id(), $motif) as [$niveau, $message]) {
                $this->addFlash($niveau, $message);
            }
            if (FicheTransition::Supprimer === $transition) {
                return $this->redirect($routes->listeUrl($type));
            }
        }

        return $this->redirect($routes->showUrl($type, $id));
    }
}
