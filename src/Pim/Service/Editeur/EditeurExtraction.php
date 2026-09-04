<?php

declare(strict_types=1);

namespace App\Pim\Service\Editeur;

use App\Ocr\Form\OcrReviewFormFactory;
use App\Ocr\Form\OcrUploadType;
use App\Ocr\Repository\DocumentExtractionRepository;
use App\Ocr\Service\OcrCategoryPolicy;
use App\Pim\Entity\Fiche;
use App\Shared\Service\ParametreProviderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Bloc extraction (OCR) de l'éditeur, en trois temps : déposer (si rien ne
 * tourne), suivre la lecture en cours, valider les valeurs lues. Une seule
 * extraction à la fois par fiche — le formulaire de dépôt disparaît tant
 * qu'une lecture n'est pas terminée. Seul fournisseur de l'éditeur qui
 * dépend du module Ocr.
 */
final readonly class EditeurExtraction
{
    public function __construct(
        private ParametreProviderInterface $parametres,
        private DocumentExtractionRepository $extractions,
        private OcrCategoryPolicy $ocrCategories,
        private OcrReviewFormFactory $ocrRevues,
        private FormFactoryInterface $forms,
        private UrlGeneratorInterface $urls,
    ) {
    }

    /** @return array{extractions: mixed, extraction: array<string, mixed>} */
    public function variables(Fiche $fiche): array
    {
        return ['extractions' => $this->extractions->history($fiche), 'extraction' => $this->bloc($fiche)];
    }

    /** @return array<string, mixed> */
    private function bloc(Fiche $fiche): array
    {
        if (!$this->parametres->bool('box.ocr_active')) {
            return ['active' => false, 'en_cours' => null, 'form_depot' => null, 'a_revoir' => null, 'form_revue' => null];
        }
        $id = $fiche->idString();
        $enCours = $this->extractions->enCours($fiche);
        $aRevoir = $this->extractions->aRevoir($fiche);

        return [
            'active' => true,
            'en_cours' => $enCours,
            'form_depot' => null === $enCours
                ? $this->forms->create(OcrUploadType::class, null, [
                    'action' => $this->urls->generate('app_mdm_fiche_extraction_deposer', ['id' => $id]),
                    'category_choices' => $this->ocrCategories->choices($fiche->type()),
                ])->createView()
                : null,
            'a_revoir' => $aRevoir,
            'form_revue' => null !== $aRevoir
                ? $this->ocrRevues->review($aRevoir, $this->urls->generate('app_mdm_fiche_extraction_valider', [
                    'id' => $id,
                    'extractionId' => $aRevoir->id(),
                ]))->createView()
                : null,
        ];
    }
}
