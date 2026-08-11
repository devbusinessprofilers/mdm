<?php

declare(strict_types=1);

namespace App\Ocr\Controller;

use App\Account\Security\FicheVoter;
use App\Account\Service\CurrentActorProvider;
use App\Dam\Enum\DocumentUsage;
use App\Ocr\Form\OcrUploadType;
use App\Ocr\Form\OcrReviewFormFactory;
use App\Ocr\Repository\DocumentExtractionRepository;
use App\Ocr\Service\OcrCategoryPolicy;
use App\Ocr\Service\OcrExtractionManager;
use App\Ocr\Service\OcrReviewException;
use App\Ocr\Service\OcrSuggestionApplier;
use App\Pim\Entity\Fiche;
use App\Pim\Service\InternalFicheMutationPolicy;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/referentiel/fiche/{id}/ocr', name: 'app_ocr_', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'])]
#[IsGranted('ROLE_BP_EDITOR')]
final class OcrController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Fiche $fiche, DocumentExtractionRepository $extractions, OcrCategoryPolicy $categories, #[Autowire('%env(bool:BOX_OCR_ENABLED)%')] bool $enabled): Response
    {
        if (!$enabled) { throw $this->createNotFoundException(); }
        $this->denyAccessUnlessGranted(FicheVoter::VIEW, $fiche);
        $form = $this->createForm(OcrUploadType::class, null, ['action' => $this->generateUrl('app_ocr_upload', ['id' => $fiche->idString()]), 'category_choices' => $categories->choices($fiche->type())]);
        return $this->render('ocr/index.html.twig', ['fiche' => $fiche, 'extractions' => $extractions->history($fiche), 'upload_form' => $form->createView(), 'category_policy' => $categories]);
    }

    #[Route('/upload', name: 'upload', methods: ['POST'])]
    public function upload(Request $request, Fiche $fiche, OcrCategoryPolicy $categories, OcrExtractionManager $manager, CurrentActorProvider $actor, #[Autowire('%env(bool:BOX_OCR_ENABLED)%')] bool $enabled): RedirectResponse
    {
        if (!$enabled) { throw $this->createNotFoundException(); }
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $fiche);
        $form = $this->createForm(OcrUploadType::class, null, ['category_choices' => $categories->choices($fiche->type())]);
        $form->handleRequest($request);
        $file = $form->isSubmitted() && $form->isValid() ? $form->get('document')->getData() : null;
        $category = $form->isSubmitted() && $form->isValid() ? $form->get('category')->getData() : null;
        if (!$file instanceof UploadedFile || !$category instanceof DocumentUsage) { $this->addFlash('error', 'Le formulaire OCR est invalide.'); }
        else {
            try { $manager->upload($fiche, $file, $category, $actor->id()); $this->addFlash('success', 'Le PDF a été placé dans la file d’extraction.'); }
            catch (\DomainException $error) { $this->addFlash('error', $error->getMessage()); }
        }
        return $this->redirectToRoute('app_ocr_index', ['id' => $fiche->idString()]);
    }

    #[Route('/{extractionId}', name: 'show', requirements: ['extractionId' => '[0-9A-HJKMNP-TV-Z]{26}'], methods: ['GET'])]
    public function show(Fiche $fiche, string $extractionId, DocumentExtractionRepository $repository, OcrReviewFormFactory $forms, OcrCategoryPolicy $categories, #[Autowire('%env(bool:BOX_OCR_ENABLED)%')] bool $enabled): Response
    {
        if (!$enabled) { throw $this->createNotFoundException(); }
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
    public function save(Request $request, Fiche $fiche, string $extractionId, DocumentExtractionRepository $repository, OcrSuggestionApplier $applier, OcrReviewFormFactory $forms, CurrentActorProvider $actor, InternalFicheMutationPolicy $mutationPolicy, #[Autowire('%env(bool:BOX_OCR_ENABLED)%')] bool $enabled): RedirectResponse
    {
        if (!$enabled) { throw $this->createNotFoundException(); }
        $this->denyAccessUnlessGranted('ROLE_BP_VALIDATOR', $fiche);
        $extraction = $repository->findForFiche($extractionId, $fiche) ?? throw $this->createNotFoundException('Extraction introuvable.');
        $form = $forms->review($extraction, $request->getUri());
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) { $this->addFlash('error', 'Le formulaire de validation OCR est invalide.'); return $this->redirectToRoute('app_ocr_show', ['id' => $fiche->idString(), 'extractionId' => $extractionId]); }
        $raw = $form->getData();
        $review = OcrReviewFormFactory::decisionsDepuisSoumission($raw);
        $request->attributes->set('_audit_source', 'box_ocr');
        try {
            $mutationPolicy->execute($fiche, function () use ($applier, $extraction, $raw, $review, $actor): void {
                $applier->apply($extraction, (int) ($raw['fiche_version'] ?? 0), $review, $actor->id());
            });
            $this->addFlash('success', 'Les décisions OCR ont été sauvegardées atomiquement.');
        }
        catch (OcrReviewException $error) { foreach ($error->errors as $message) { $this->addFlash('error', $message); } }
        return $this->redirectToRoute('app_ocr_show', ['id' => $fiche->idString(), 'extractionId' => $extractionId]);
    }

    #[Route('/{extractionId}/retry', name: 'retry', requirements: ['extractionId' => '[0-9A-HJKMNP-TV-Z]{26}'], methods: ['POST'])]
    public function retry(Request $request, Fiche $fiche, string $extractionId, DocumentExtractionRepository $repository, OcrExtractionManager $manager, OcrReviewFormFactory $forms, CurrentActorProvider $actor, #[Autowire('%env(bool:BOX_OCR_ENABLED)%')] bool $enabled): RedirectResponse
    {
        if (!$enabled) { throw $this->createNotFoundException(); }
        $this->denyAccessUnlessGranted('ROLE_BP_VALIDATOR', $fiche);
        $failed = $repository->findForFiche($extractionId, $fiche) ?? throw $this->createNotFoundException('Extraction introuvable.');
        $form = $forms->retry($failed, $request->getUri());
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) { throw $this->createAccessDeniedException('Action de relance invalide.'); }
        try { $next = $manager->retry($failed, $actor->id()); $this->addFlash('success', 'Une nouvelle extraction a été créée.'); return $this->redirectToRoute('app_ocr_show', ['id' => $fiche->idString(), 'extractionId' => $next->id()]); }
        catch (\DomainException $error) { $this->addFlash('error', $error->getMessage()); return $this->redirectToRoute('app_ocr_show', ['id' => $fiche->idString(), 'extractionId' => $failed->id()]); }
    }

}
