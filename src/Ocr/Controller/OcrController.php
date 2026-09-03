<?php

declare(strict_types=1);

namespace App\Ocr\Controller;

use App\Account\Security\FicheVoter;
use App\Account\Service\CurrentActorProvider;
use App\Ocr\Form\OcrUploadType;
use App\Ocr\Form\OcrReviewFormFactory;
use App\Ocr\Repository\DocumentExtractionRepository;
use App\Ocr\Service\OcrActions;
use App\Ocr\Service\OcrCategoryPolicy;
use App\Ocr\Service\OcrExtractionManager;
use App\Pim\Entity\Fiche;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Écran OCR d'une fiche : historique des extractions, détail et relecture.
 * Le dépôt et l'application des décisions passent par OcrActions, partagé
 * avec l'éditeur de fiche.
 */
#[Route('/referentiel/fiche/{id}/ocr', name: 'app_ocr_', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'])]
#[IsGranted('ROLE_BP_EDITOR')]
final class OcrController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Fiche $fiche, DocumentExtractionRepository $extractions, OcrCategoryPolicy $categories, OcrActions $actions): Response
    {
        if (!$actions->actif()) { throw $this->createNotFoundException(); }
        $this->denyAccessUnlessGranted(FicheVoter::VIEW, $fiche);
        $form = $this->createForm(OcrUploadType::class, null, ['action' => $this->generateUrl('app_ocr_upload', ['id' => $fiche->idString()]), 'category_choices' => $categories->choices($fiche->type())]);
        return $this->render('ocr/index.html.twig', ['fiche' => $fiche, 'extractions' => $extractions->history($fiche), 'upload_form' => $form->createView(), 'category_policy' => $categories]);
    }

    #[Route('/upload', name: 'upload', methods: ['POST'])]
    public function upload(Request $request, Fiche $fiche, OcrActions $actions, CurrentActorProvider $actor): RedirectResponse
    {
        if (!$actions->actif()) { throw $this->createNotFoundException(); }
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $fiche);
        [$type, $message] = $actions->deposer($request, $fiche, $actor->id());
        $this->addFlash($type, $message);
        return $this->redirectToRoute('app_ocr_index', ['id' => $fiche->idString()]);
    }

    #[Route('/{extractionId}', name: 'show', requirements: ['extractionId' => '[0-9A-HJKMNP-TV-Z]{26}'], methods: ['GET'])]
    public function show(Fiche $fiche, string $extractionId, DocumentExtractionRepository $repository, OcrReviewFormFactory $forms, OcrCategoryPolicy $categories, OcrActions $actions): Response
    {
        if (!$actions->actif()) { throw $this->createNotFoundException(); }
        $this->denyAccessUnlessGranted(FicheVoter::VIEW, $fiche);
        $extraction = $repository->findForFiche($extractionId, $fiche) ?? throw $this->createNotFoundException('Extraction introuvable.');
        return $this->render('ocr/show.html.twig', [
            'fiche' => $fiche, 'extraction' => $extraction,
            'category_label' => $categories->label($extraction->documentCategory()),
            'review_form' => $forms->review($extraction, $this->generateUrl('app_ocr_save', ['id' => $fiche->idString(), 'extractionId' => $extraction->id()]))->createView(),
            'retry_form' => $forms->retry($extraction, $this->generateUrl('app_ocr_retry', ['id' => $fiche->idString(), 'extractionId' => $extraction->id()]))->createView(),
        ]);
    }

    #[Route('/{extractionId}/save', name: 'save', requirements: ['extractionId' => '[0-9A-HJKMNP-TV-Z]{26}'], methods: ['POST'])]
    public function save(Request $request, Fiche $fiche, string $extractionId, DocumentExtractionRepository $repository, OcrActions $actions, CurrentActorProvider $actor): RedirectResponse
    {
        if (!$actions->actif()) { throw $this->createNotFoundException(); }
        $this->denyAccessUnlessGranted('ROLE_BP_VALIDATOR');
        $extraction = $repository->findForFiche($extractionId, $fiche) ?? throw $this->createNotFoundException('Extraction introuvable.');
        foreach ($actions->valider($request, $fiche, $extraction, $actor->id()) as [$type, $message]) {
            $this->addFlash($type, $message);
        }
        return $this->redirectToRoute('app_ocr_show', ['id' => $fiche->idString(), 'extractionId' => $extractionId]);
    }

    #[Route('/{extractionId}/retry', name: 'retry', requirements: ['extractionId' => '[0-9A-HJKMNP-TV-Z]{26}'], methods: ['POST'])]
    public function retry(Request $request, Fiche $fiche, string $extractionId, DocumentExtractionRepository $repository, OcrExtractionManager $manager, OcrReviewFormFactory $forms, CurrentActorProvider $actor, OcrActions $actions): RedirectResponse
    {
        if (!$actions->actif()) { throw $this->createNotFoundException(); }
        $this->denyAccessUnlessGranted('ROLE_BP_VALIDATOR');
        $failed = $repository->findForFiche($extractionId, $fiche) ?? throw $this->createNotFoundException('Extraction introuvable.');
        $form = $forms->retry($failed, $request->getUri());
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) { throw $this->createAccessDeniedException('Action de relance invalide.'); }
        try { $next = $manager->retry($failed, $actor->id()); $this->addFlash('success', 'Une nouvelle extraction a été créée.'); return $this->redirectToRoute('app_ocr_show', ['id' => $fiche->idString(), 'extractionId' => $next->id()]); }
        catch (\DomainException $error) { $this->addFlash('error', $error->getMessage()); return $this->redirectToRoute('app_ocr_show', ['id' => $fiche->idString(), 'extractionId' => $failed->id()]); }
    }

}
