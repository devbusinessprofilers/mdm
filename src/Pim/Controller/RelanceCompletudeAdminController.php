<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Pim\Entity\FicheRelancePlanifiee;
use App\Pim\Message\EnvoyerRelancesPlanifiees;
use App\Pim\Repository\FicheRelancePlanifieeRepository;
use App\Pim\Repository\FicheRelanceRepository;
use App\Pim\Service\FicheRouteResolver;
use App\Pim\Service\RelanceCompletudeAdminManager;
use App\Pim\Service\RelanceCompletudePlanificateur;
use App\Shared\Entity\Parametre;
use App\Shared\Form\ActionType;
use App\Shared\Repository\ParametreRepository;
use App\Shared\Service\ParametreAdminManager;
use App\Shared\Service\ParametreProviderInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Ulid;

/**
 * Dashboard de contrôle des relances de complétude : état de l'envoi
 * automatique (paramètre completude.rappel_auto_actif), lot planifié par la
 * préparation du lundi 8h avec exclusion ligne à ligne avant l'envoi de 14h,
 * actions manuelles (relancer l'analyse, envoyer maintenant) et historique
 * des relances envoyées.
 */
#[Route('/admin/relances-completude', name: 'app_pim_relance_completude_')]
#[IsGranted('ROLE_ADMIN')]
final class RelanceCompletudeAdminController extends AbstractController
{
    private const ID_REQUIREMENT = '[0-9A-HJKMNP-TV-Z]{26}';
    private const PARAMETRE_ENVOI_AUTO = 'completude.rappel_auto_actif';

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        FicheRelancePlanifieeRepository $planifiees,
        FicheRelanceRepository $relances,
        ParametreProviderInterface $parametres,
        FicheRouteResolver $ficheRoutes,
        FormFactoryInterface $forms,
    ): Response {
        $envoiAutoActif = $parametres->bool(self::PARAMETRE_ENVOI_AUTO);
        $lignes = [];
        foreach ($planifiees->lotCourant() as $planifiee) {
            $lignes[] = [
                'planifiee' => $planifiee,
                'fiche_url' => $ficheRoutes->editUrl($planifiee->fiche()->type(), $planifiee->fiche()->idString()),
                'exclure_form' => !$planifiee->estPlanifiee() ? null : $forms->createNamed('action', ActionType::class, null, [
                    'action' => $this->generateUrl('app_pim_relance_completude_exclure', ['id' => $planifiee->id()]),
                    'button_label' => 'Exclure',
                    'csrf_token_id' => 'relance-exclure-'.$planifiee->id(),
                ])->createView(),
            ];
        }

        return $this->render('pim/relance_completude_admin.html.twig', [
            'envoi_auto_actif' => $envoiAutoActif,
            'seuil_rappel' => $parametres->int('completude.seuil_rappel'),
            'lignes' => $lignes,
            'historique' => $relances->dernieres(),
            'toggle_form' => $forms->createNamed('action', ActionType::class, null, [
                'action' => $this->generateUrl('app_pim_relance_completude_envoi_auto'),
                'button_label' => $envoiAutoActif ? 'Désactiver l’envoi automatique' : 'Activer l’envoi automatique',
                'csrf_token_id' => 'relance-envoi-auto',
            ])->createView(),
            'analyse_form' => $forms->createNamed('action', ActionType::class, null, [
                'action' => $this->generateUrl('app_pim_relance_completude_analyser'),
                'button_label' => 'Relancer l’analyse',
                'csrf_token_id' => 'relance-analyse',
            ])->createView(),
            'envoi_form' => $forms->createNamed('action', ActionType::class, null, [
                'action' => $this->generateUrl('app_pim_relance_completude_envoyer'),
                'button_label' => 'Envoyer maintenant',
                'csrf_token_id' => 'relance-envoi',
            ])->createView(),
        ]);
    }

    #[Route('/{id}/exclure', name: 'exclure', requirements: ['id' => self::ID_REQUIREMENT], methods: ['POST'])]
    public function exclure(
        Request $request,
        string $id,
        FicheRelancePlanifieeRepository $planifiees,
        RelanceCompletudeAdminManager $manager,
        FormFactoryInterface $forms,
    ): Response {
        $planifiee = $planifiees->find(Ulid::fromString($id));
        if (!$planifiee instanceof FicheRelancePlanifiee) {
            throw $this->createNotFoundException('Relance planifiée introuvable.');
        }
        $form = $forms->createNamed('action', ActionType::class, null, [
            'button_label' => 'Exclure',
            'csrf_token_id' => 'relance-exclure-'.$id,
        ]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            throw $this->createAccessDeniedException('Action invalide.');
        }
        if (!$planifiee->estPlanifiee()) {
            $this->addFlash('warning', 'Cette relance a déjà été traitée, elle ne peut plus être exclue.');
        } else {
            $manager->exclure($planifiee);
            $fiche = $planifiee->fiche();
            $this->addFlash('success', sprintf('Relance de la fiche « %s » exclue du lot.', $fiche->label() ?: sprintf('Fiche %d', $fiche->code())));
        }

        return $this->redirectToRoute('app_pim_relance_completude_index');
    }

    #[Route('/analyser', name: 'analyser', methods: ['POST'])]
    public function analyser(
        Request $request,
        RelanceCompletudePlanificateur $planificateur,
        FormFactoryInterface $forms,
    ): Response {
        $form = $forms->createNamed('action', ActionType::class, null, [
            'button_label' => 'Relancer l’analyse',
            'csrf_token_id' => 'relance-analyse',
        ]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            throw $this->createAccessDeniedException('Action invalide.');
        }
        $count = $planificateur->preparer();
        $this->addFlash('success', sprintf('Analyse terminée : %d relance(s) planifiée(s).', $count));

        return $this->redirectToRoute('app_pim_relance_completude_index');
    }

    #[Route('/envoyer', name: 'envoyer', methods: ['POST'])]
    public function envoyer(
        Request $request,
        MessageBusInterface $bus,
        FormFactoryInterface $forms,
    ): Response {
        $form = $forms->createNamed('action', ActionType::class, null, [
            'button_label' => 'Envoyer maintenant',
            'csrf_token_id' => 'relance-envoi',
        ]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            throw $this->createAccessDeniedException('Action invalide.');
        }
        $bus->dispatch(new EnvoyerRelancesPlanifiees(force: true));
        $this->addFlash('success', 'Envoi du lot déclenché : les mails partent d’ici quelques minutes via les workers.');

        return $this->redirectToRoute('app_pim_relance_completude_index');
    }

    #[Route('/envoi-auto', name: 'envoi_auto', methods: ['POST'])]
    public function basculerEnvoiAuto(
        Request $request,
        ParametreRepository $parametres,
        ParametreAdminManager $manager,
        ParametreProviderInterface $provider,
        FormFactoryInterface $forms,
    ): Response {
        $parametre = $parametres->parNom(self::PARAMETRE_ENVOI_AUTO);
        if (!$parametre instanceof Parametre) {
            throw $this->createNotFoundException('Paramètre introuvable.');
        }
        $actif = $provider->bool(self::PARAMETRE_ENVOI_AUTO);
        $form = $forms->createNamed('action', ActionType::class, null, [
            'button_label' => $actif ? 'Désactiver l’envoi automatique' : 'Activer l’envoi automatique',
            'csrf_token_id' => 'relance-envoi-auto',
        ]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            throw $this->createAccessDeniedException('Action invalide.');
        }
        $manager->surcharger($parametre, !$actif);
        $this->addFlash('success', $actif
            ? 'Envoi automatique désactivé : le lot du lundi restera en attente jusqu’à un envoi manuel.'
            : 'Envoi automatique activé : le lot planifié partira le lundi à 14h.');

        return $this->redirectToRoute('app_pim_relance_completude_index');
    }
}
