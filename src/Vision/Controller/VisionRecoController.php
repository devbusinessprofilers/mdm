<?php

declare(strict_types=1);

namespace App\Vision\Controller;

use App\Account\Service\CurrentActorProvider;
use App\Shared\Service\ParametreProviderInterface;
use App\Vision\Entity\ImageRecognition;
use App\Vision\Form\VisionFormFactory;
use App\Vision\Repository\ImageRecognitionRepository;
use App\Vision\Service\ImageRecognitionApplier;
use App\Vision\Service\ImageRecognitionManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Actions de l'onglet « Reconnaissance IA » de /medias : lancement manuel sur
 * une sélection de fiches, revue des suggestions, relance des analyses.
 * Toutes 404 tant que l'IA n'est pas activée (openai.actif).
 */
#[Route('/medias/reco', name: 'app_mdm_reco_')]
final class VisionRecoController extends AbstractController
{
    #[Route('/lancer', name: 'lancer', methods: ['POST'])]
    #[IsGranted('ROLE_BP_EDITOR')]
    public function lancer(Request $request, VisionFormFactory $forms, ImageRecognitionManager $manager, CurrentActorProvider $actor, ParametreProviderInterface $parametres): RedirectResponse
    {
        if (!$parametres->bool('openai.actif')) {
            throw $this->createNotFoundException();
        }
        $form = $forms->lancementReco();
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'La sélection de fiches est invalide.');

            return $this->redirectToRoute('app_mdm_medias', ['onglet' => 'ia']);
        }
        $fiches = iterator_to_array($form->get('fiches')->getData() ?? [], false);
        try {
            $launched = $manager->launchForFiches($fiches, $actor->id());
            $this->addFlash(
                $launched > 0 ? 'success' : 'error',
                $launched > 0
                    ? sprintf('%d reconnaissance%s placée%s dans la file.', $launched, $launched > 1 ? 's' : '', $launched > 1 ? 's' : '')
                    : 'Aucune photo exploitable : les photos doivent être traitées et sans analyse en cours.',
            );
        } catch (\DomainException $error) {
            $this->addFlash('error', $error->getMessage());
        }

        return $this->redirectToRoute('app_mdm_medias', ['onglet' => 'ia']);
    }

    #[Route('/{id}/valider', name: 'valider', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], methods: ['POST'])]
    #[IsGranted('ROLE_BP_VALIDATOR')]
    public function valider(string $id, Request $request, ImageRecognitionRepository $recognitions, VisionFormFactory $forms, ImageRecognitionApplier $applier, CurrentActorProvider $actor, ParametreProviderInterface $parametres): RedirectResponse
    {
        if (!$parametres->bool('openai.actif')) {
            throw $this->createNotFoundException();
        }
        $recognition = $recognitions->find($id);
        if (!$recognition instanceof ImageRecognition) {
            throw $this->createNotFoundException('Reconnaissance introuvable.');
        }
        $form = $forms->review($recognition, $request->getUri());
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'Le formulaire de validation est invalide.');

            return $this->retour($request);
        }
        $decisions = VisionFormFactory::decisionsDepuisSoumission((array) $form->getData());
        if ([] === $decisions) {
            $this->addFlash('error', 'Aucune décision : validez ou refusez au moins une suggestion.');

            return $this->retour($request);
        }
        try {
            $applier->apply($recognition, $decisions, $actor->id());
            $this->addFlash('success', 'Les décisions ont été appliquées sur la fiche.');
        } catch (\DomainException $error) {
            $this->addFlash('error', $error->getMessage());
        }

        return $this->retour($request);
    }

    #[Route('/{id}/relancer', name: 'relancer', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], methods: ['POST'])]
    #[IsGranted('ROLE_BP_EDITOR')]
    public function relancer(string $id, Request $request, ImageRecognitionManager $manager, ParametreProviderInterface $parametres): RedirectResponse
    {
        if (!$parametres->bool('openai.actif')) {
            throw $this->createNotFoundException();
        }
        if (!$this->isCsrfTokenValid('vision-reco-relancer-'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        try {
            $manager->retry($id);
            $this->addFlash('success', 'La reconnaissance a été replacée dans la file.');
        } catch (\DomainException $error) {
            $this->addFlash('error', $error->getMessage());
        }

        return $this->retour($request);
    }

    public function retour(Request $request): RedirectResponse
    {
        return $this->redirectToRoute('app_mdm_medias', ['onglet' => 'ia', 'page' => $request->query->getInt('page', 1)]);
    }
}
