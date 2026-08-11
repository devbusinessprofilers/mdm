<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Account\Security\FicheVoter;
use App\Account\Service\CurrentActorProvider;
use App\Dam\Enum\DocumentUsage;
use App\Ocr\Form\OcrReviewFormFactory;
use App\Ocr\Form\OcrUploadType;
use App\Ocr\Repository\DocumentExtractionRepository;
use App\Ocr\Service\OcrCategoryPolicy;
use App\Ocr\Service\OcrExtractionManager;
use App\Ocr\Service\OcrReviewException;
use App\Ocr\Service\OcrSuggestionApplier;
use App\Pim\Entity\Fiche;
use App\Pim\Service\FicheEditeurEcran;
use App\Pim\Service\InternalFicheMutationPolicy;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Extraction OCR depuis l'éditeur de fiche, en trois temps comme la maquette :
 * on dépose, la lecture court en tâche de fond (l'éditeur affiche son état),
 * l'humain valide champ par champ. Le mécanisme est celui de l'écran OCR
 * existant ; seul l'emplacement change. Une seule extraction à la fois par
 * fiche — la règle est portée par OcrExtractionManager.
 */
#[Route('/referentiel/fiche/{id}/extraction', name: 'app_mdm_fiche_extraction_', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'])]
final class FicheExtractionController extends AbstractController
{
    #[Route('/deposer', name: 'deposer', methods: ['POST'])]
    public function deposer(
        Request $request,
        Fiche $fiche,
        OcrCategoryPolicy $categories,
        OcrExtractionManager $manager,
        CurrentActorProvider $actor,
        FicheEditeurEcran $ecran,
        #[Autowire('%env(bool:BOX_OCR_ENABLED)%')] bool $enabled,
    ): RedirectResponse {
        if (!$enabled) {
            throw $this->createNotFoundException();
        }
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $fiche);
        $retour = new RedirectResponse($ecran->urlExtraction($fiche->type(), $fiche->idString()));
        $form = $this->createForm(OcrUploadType::class, null, ['category_choices' => $categories->choices($fiche->type())]);
        $form->handleRequest($request);
        $file = $form->isSubmitted() && $form->isValid() ? $form->get('document')->getData() : null;
        $category = $form->isSubmitted() && $form->isValid() ? $form->get('category')->getData() : null;
        if (!$file instanceof UploadedFile || !$category instanceof DocumentUsage) {
            $this->addFlash('error', 'Le dépôt est invalide : un PDF et une catégorie documentaire sont requis.');

            return $retour;
        }
        try {
            $manager->upload($fiche, $file, $category, $actor->id());
            $this->addFlash('success', 'Document déposé : la lecture démarre. Vous pouvez continuer à travailler, les valeurs lues vous attendront ici.');
        } catch (\DomainException $error) {
            $this->addFlash('error', $error->getMessage());
        }

        return $retour;
    }

    #[Route('/{extractionId}/valider', name: 'valider', requirements: ['extractionId' => '[0-9A-HJKMNP-TV-Z]{26}'], methods: ['POST'])]
    public function valider(
        Request $request,
        Fiche $fiche,
        string $extractionId,
        DocumentExtractionRepository $extractions,
        OcrReviewFormFactory $forms,
        OcrSuggestionApplier $applier,
        InternalFicheMutationPolicy $mutationPolicy,
        CurrentActorProvider $actor,
        FicheEditeurEcran $ecran,
        #[Autowire('%env(bool:BOX_OCR_ENABLED)%')] bool $enabled,
    ): RedirectResponse {
        if (!$enabled) {
            throw $this->createNotFoundException();
        }
        $this->denyAccessUnlessGranted('ROLE_BP_VALIDATOR');
        $extraction = $extractions->findForFiche($extractionId, $fiche)
            ?? throw $this->createNotFoundException('Extraction introuvable.');
        $retour = new RedirectResponse($ecran->urlExtraction($fiche->type(), $fiche->idString()));
        $form = $forms->review($extraction, $request->getUri());
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'Le formulaire de validation des valeurs lues est invalide.');

            return $retour;
        }
        $raw = $form->getData();
        $review = OcrReviewFormFactory::decisionsDepuisSoumission($raw);
        $request->attributes->set('_audit_source', 'box_ocr');
        try {
            $mutationPolicy->execute($fiche, function () use ($applier, $extraction, $raw, $review, $actor): void {
                $applier->apply($extraction, (int) ($raw['fiche_version'] ?? 0), $review, $actor->id());
            });
            $this->addFlash('success', 'Vos décisions ont été appliquées à la fiche.');
        } catch (OcrReviewException $error) {
            foreach ($error->errors as $message) {
                $this->addFlash('error', $message);
            }
        }

        return $retour;
    }
}
