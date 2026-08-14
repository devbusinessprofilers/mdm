<?php

declare(strict_types=1);

namespace App\Ocr\MessageHandler;

use App\Ocr\Entity\DocumentExtraction;
use App\Ocr\Enum\ExtractionStatus;
use App\Ocr\Message\AutoApplyOcrSuggestions;
use App\Ocr\Repository\DocumentExtractionRepository;
use App\Ocr\Service\OcrReviewException;
use App\Ocr\Service\OcrSuggestionApplier;
use App\Shared\Service\ParametreProviderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Applique automatiquement les suggestions OCR dont la confiance atteint le
 * seuil paramétré (ocr.seuil_application_auto, en % — 0 = tout manuel,
 * lu à l'exécution). Écriture technique : aucune transition de workflow.
 * Une valeur refusée par les validations métier reste simplement en
 * attente d'arbitrage humain.
 */
#[AsMessageHandler]
final readonly class AutoApplyOcrSuggestionsHandler
{
    public function __construct(
        private DocumentExtractionRepository $extractions,
        private OcrSuggestionApplier $applier,
        private ParametreProviderInterface $parametres,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(AutoApplyOcrSuggestions $message): void
    {
        $seuil = $this->parametres->int('ocr.seuil_application_auto');
        if ($seuil <= 0) {
            return;
        }
        $extraction = $this->extractions->find($message->extractionId);
        if (!$extraction instanceof DocumentExtraction
            || !in_array($extraction->status(), [ExtractionStatus::Ready, ExtractionStatus::PartiallyReviewed], true)) {
            return;
        }
        $review = [];
        foreach ($extraction->suggestions() as $suggestion) {
            if (!$suggestion->isPending()) {
                continue;
            }
            $confidence = $suggestion->confidence();
            if (null === $confidence || $confidence * 100 < $seuil) {
                continue;
            }
            $review[$suggestion->id()] = [
                'value' => $suggestion->correctedValue(),
                'accept' => true,
                'reject' => false,
            ];
        }
        if ([] === $review) {
            return;
        }
        $fiche = $extraction->fiche();
        try {
            $fiche->preserveWorkflowDuring(function () use ($extraction, $fiche, $review, $seuil): void {
                $this->applier->apply($extraction, $fiche->version(), $review, sprintf('automatique (confiance ≥ %d %%)', $seuil));
            });
            $this->logger->info('Suggestions OCR appliquées automatiquement.', [
                'extraction' => $extraction->id(),
                'fiche' => $fiche->idString(),
                'appliquees' => count($review),
                'seuil' => $seuil,
            ]);
        } catch (OcrReviewException $error) {
            // Les valeurs restent en attente : l'arbitrage humain tranchera.
            $this->logger->warning('Application automatique des suggestions OCR refusée par les validations.', [
                'extraction' => $extraction->id(),
                'erreurs' => $error->errors,
            ]);
        }
    }
}
