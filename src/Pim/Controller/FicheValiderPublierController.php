<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Account\Security\FicheVoter;
use App\Account\Service\CurrentActorProvider;
use App\Etl\Service\PhotoPublicationGuard;
use App\Pim\Entity\Fiche;
use App\Pim\Form\FicheActionFormFactory;
use App\Pim\Repository\FicheRepository;
use App\Pim\Service\FicheWorkflowManager;
use App\Pim\Service\LieuObligationsPublication;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Ulid;

/**
 * Bouton « Valider et publier » d'une fiche en attente de validation : les deux
 * transitions en un clic quand la publication est possible ; sinon la fiche
 * reste validée et le motif du blocage (obligations photos ou champs
 * obligatoires de la bible, comme la publication de masse) est expliqué.
 */
final class FicheValiderPublierController extends AbstractController
{
    #[Route('/referentiel/fiche/{id}/valider-publier', name: 'app_pim_fiche_valider_publier', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], methods: ['POST'])]
    public function __invoke(
        string $id,
        Request $request,
        FicheRepository $fiches,
        FicheActionFormFactory $formFactory,
        FicheWorkflowManager $workflow,
        CurrentActorProvider $actor,
        PhotoPublicationGuard $photoGuard,
    ): Response {
        $fiche = $fiches->find(Ulid::fromString($id));
        if (!$fiche instanceof Fiche) {
            throw $this->createNotFoundException('Fiche introuvable.');
        }
        $this->denyAccessUnlessGranted(FicheVoter::VALIDATE, $fiche);
        $this->denyAccessUnlessGranted(FicheVoter::PUBLISH, $fiche);

        $form = $formFactory->validerPublier($id);
        $form->handleRequest($request);
        $retour = $this->redirect($request->headers->get('referer') ?? $this->generateUrl('app_mdm_referentiel_general'));
        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('warning', 'Action non confirmée (jeton invalide).');

            return $retour;
        }
        try {
            $photosOk = $photoGuard->compliant($fiche);
            $publiee = $workflow->validateAndPublish($fiche, $actor->id());
        } catch (\DomainException $exception) {
            $this->addFlash('warning', $exception->getMessage());

            return $retour;
        }
        if ($publiee) {
            $this->addFlash('success', 'Fiche validée et publiée.');
        } else {
            $manquants = $workflow->champsObligatoiresManquants($fiche);
            $this->addFlash('warning', [] === $manquants
                ? 'Fiche validée, mais non publiée : obligations photos non satisfaites (minimum de photos du type).'
                : 'Fiche validée, mais non publiée. '.LieuObligationsPublication::motif($manquants)
                    .($photosOk ? '' : ' Obligations photos non satisfaites (minimum de photos du type).'));
        }

        return $retour;
    }
}
