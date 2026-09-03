<?php

declare(strict_types=1);

namespace App\Ocr\Service;

use App\Dam\Enum\DocumentUsage;
use App\Ocr\Entity\DocumentExtraction;
use App\Ocr\Form\OcrReviewFormFactory;
use App\Ocr\Form\OcrUploadType;
use App\Pim\Entity\Fiche;
use App\Pim\Service\InternalFicheMutationPolicy;
use App\Shared\Service\ParametreProviderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

/**
 * Les deux gestes OCR partagés par l'écran OCR et l'éditeur de fiche :
 * déposer un PDF, puis appliquer les décisions de relecture. Les contrôleurs
 * ne gardent que l'autorisation et la redirection ; la gate est unique
 * (paramètre `box.ocr_active`, surchargeable dans l'administration).
 */
final readonly class OcrActions
{
    public function __construct(
        private FormFactoryInterface $forms,
        private OcrCategoryPolicy $categories,
        private OcrExtractionManager $manager,
        private OcrReviewFormFactory $reviewForms,
        private OcrSuggestionApplier $applier,
        private InternalFicheMutationPolicy $mutationPolicy,
        private ParametreProviderInterface $parametres,
    ) {
    }

    public function actif(): bool
    {
        return $this->parametres->bool('box.ocr_active');
    }

    /**
     * Dépose le PDF soumis et lance la lecture en tâche de fond.
     *
     * @return array{0: string, 1: string} type et message du flash à afficher
     */
    public function deposer(Request $request, Fiche $fiche, string $actor): array
    {
        $form = $this->forms->create(OcrUploadType::class, null, ['category_choices' => $this->categories->choices($fiche->type())]);
        $form->handleRequest($request);
        $valide = $form->isSubmitted() && $form->isValid();
        $file = $valide ? $form->get('document')->getData() : null;
        $category = $valide ? $form->get('category')->getData() : null;
        if (!$file instanceof UploadedFile || !$category instanceof DocumentUsage) {
            return ['error', 'Le dépôt est invalide : un PDF et une catégorie documentaire sont requis.'];
        }
        try {
            $this->manager->upload($fiche, $file, $category, $actor);
        } catch (\DomainException $error) {
            return ['error', $error->getMessage()];
        }

        return ['success', 'Document déposé : la lecture démarre. Vous pouvez continuer à travailler, les valeurs lues vous attendront ici.'];
    }

    /**
     * Applique à la fiche les décisions prises champ par champ.
     *
     * @return list<array{0: string, 1: string}> flashs à afficher
     */
    public function valider(Request $request, Fiche $fiche, DocumentExtraction $extraction, string $actor): array
    {
        $form = $this->reviewForms->review($extraction, $request->getUri());
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return [['error', 'Le formulaire de validation des valeurs lues est invalide.']];
        }
        $raw = $form->getData();
        $review = OcrReviewFormFactory::decisionsDepuisSoumission($raw);
        $request->attributes->set('_audit_source', 'box_ocr');
        try {
            $this->mutationPolicy->execute($fiche, function () use ($extraction, $raw, $review, $actor): void {
                $this->applier->apply($extraction, (int) ($raw['fiche_version'] ?? 0), $review, $actor);
            });
        } catch (OcrReviewException $error) {
            $flashs = [];
            foreach ($error->errors as $message) {
                $flashs[] = ['error', $message];
            }

            return $flashs;
        }

        return [['success', 'Vos décisions ont été appliquées à la fiche.']];
    }
}
