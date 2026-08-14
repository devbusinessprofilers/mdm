<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Account\Security\FicheVoter;
use App\Account\Service\CurrentActorProvider;
use App\Dam\Enum\DocumentUsage;
use App\Ocr\Entity\OcrSuggestion;
use App\Ocr\Form\OcrReviewFormFactory;
use App\Ocr\Form\OcrUploadType;
use App\Ocr\Repository\DocumentExtractionRepository;
use App\Ocr\Service\OcrCategoryPolicy;
use App\Ocr\Service\OcrExtractionManager;
use App\Ocr\Service\OcrReviewException;
use App\Ocr\Service\OcrSuggestionApplier;
use App\Pim\Entity\Fiche;
use App\Pim\Service\FicheEditeurEcran;
use App\Pim\Service\FicheSectionsCatalogue;
use App\Pim\Service\InternalFicheMutationPolicy;
use App\Shared\Form\ActionType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormFactoryInterface;
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

    /**
     * Décision en un clic depuis le bloc « Suggestions IA en attente » de
     * l'onglet Informations générales : Accepter applique immédiatement la
     * valeur lue (mêmes validations que la revue complète), Ignorer la
     * rejette. La valeur n'est pas corrigible ici — passer par la revue.
     */
    #[Route('/suggestion/{suggestionId}/{decision}', name: 'suggestion', requirements: ['suggestionId' => '[0-9A-HJKMNP-TV-Z]{26}', 'decision' => 'accepter|ignorer'], methods: ['POST'])]
    public function suggestion(
        Request $request,
        Fiche $fiche,
        string $suggestionId,
        string $decision,
        DocumentExtractionRepository $extractions,
        FormFactoryInterface $forms,
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
        $suggestion = $extractions->suggestionPourFiche($suggestionId, $fiche)
            ?? throw $this->createNotFoundException('Suggestion introuvable.');
        $retour = new RedirectResponse($ecran->urlSection(
            $fiche->type(),
            $fiche->idString(),
            FicheSectionsCatalogue::indexBloc($fiche->type(), 'suggestions_attente'),
        ));
        // Même nom et même jeton que le formulaire rendu par l'éditeur.
        $form = $forms->createNamed('suggestion_'.$decision.'_'.$suggestionId, ActionType::class, null, [
            'button_label' => 'accepter' === $decision ? 'Accepter' : 'Ignorer',
            'csrf_token_id' => 'suggestion-'.$decision.'-'.$suggestionId,
        ]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'La décision est invalide (jeton expiré ?). Rechargez la page.');

            return $retour;
        }
        if (!$suggestion->isPending()) {
            $this->addFlash('error', 'Cette suggestion a déjà été arbitrée.');

            return $retour;
        }
        $request->attributes->set('_audit_source', 'box_ocr');
        $review = [$suggestionId => [
            'value' => $suggestion->correctedValue(),
            'accept' => 'accepter' === $decision,
            'reject' => 'ignorer' === $decision,
        ]];
        try {
            $mutationPolicy->execute($fiche, function () use ($applier, $suggestion, $fiche, $review, $actor): void {
                $applier->apply($suggestion->extraction(), $fiche->version(), $review, $actor->id());
            });
            $this->addFlash('success', 'accepter' === $decision
                ? sprintf('« %s » appliqué à la fiche.', $suggestion->label())
                : sprintf('Suggestion « %s » ignorée.', $suggestion->label()));
        } catch (OcrReviewException $error) {
            foreach ($error->errors as $message) {
                $this->addFlash('error', $message);
            }
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
