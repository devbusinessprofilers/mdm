<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Account\Security\FicheVoter;
use App\Account\Service\CurrentActorProvider;
use App\Ocr\Repository\DocumentExtractionRepository;
use App\Ocr\Service\OcrActions;
use App\Pim\Entity\Fiche;
use App\Pim\Service\Editeur\EditeurNavigation;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Extraction OCR depuis l'éditeur de fiche, en trois temps comme la maquette :
 * on dépose, la lecture court en tâche de fond (l'éditeur affiche son état),
 * l'humain valide champ par champ. Les gestes sont ceux de l'écran OCR
 * (OcrActions) ; seuls l'autorisation et le retour vers l'éditeur vivent ici.
 */
#[Route('/referentiel/fiche/{id}/extraction', name: 'app_mdm_fiche_extraction_', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'])]
final class FicheExtractionController extends AbstractController
{
    #[Route('/deposer', name: 'deposer', methods: ['POST'])]
    public function deposer(
        Request $request,
        Fiche $fiche,
        OcrActions $actions,
        CurrentActorProvider $actor,
        EditeurNavigation $navigation,
    ): RedirectResponse {
        if (!$actions->actif()) {
            throw $this->createNotFoundException();
        }
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $fiche);
        [$type, $message] = $actions->deposer($request, $fiche, $actor->id());
        $this->addFlash($type, $message);

        return new RedirectResponse($navigation->urlExtraction($fiche->type(), $fiche->idString()));
    }

    #[Route('/{extractionId}/valider', name: 'valider', requirements: ['extractionId' => '[0-9A-HJKMNP-TV-Z]{26}'], methods: ['POST'])]
    public function valider(
        Request $request,
        Fiche $fiche,
        string $extractionId,
        DocumentExtractionRepository $extractions,
        OcrActions $actions,
        CurrentActorProvider $actor,
        EditeurNavigation $navigation,
    ): RedirectResponse {
        if (!$actions->actif()) {
            throw $this->createNotFoundException();
        }
        $this->denyAccessUnlessGranted('ROLE_BP_VALIDATOR');
        $extraction = $extractions->findForFiche($extractionId, $fiche)
            ?? throw $this->createNotFoundException('Extraction introuvable.');
        foreach ($actions->valider($request, $fiche, $extraction, $actor->id()) as [$type, $message]) {
            $this->addFlash($type, $message);
        }

        return new RedirectResponse($navigation->urlExtraction($fiche->type(), $fiche->idString()));
    }
}
