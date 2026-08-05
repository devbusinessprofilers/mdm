<?php

declare(strict_types=1);

namespace App\Tests\Ocr;

use App\Dam\Entity\MediaAsset;
use App\Dam\Enum\MediaKind;
use App\Ocr\Entity\DocumentExtraction;
use App\Ocr\Entity\OcrSuggestion;
use App\Ocr\Enum\ExtractionStatus;
use App\Ocr\Enum\SuggestionStatus;
use App\Ocr\Service\OcrExtractionConsolidator;
use App\Pim\Entity\Fiche;
use App\Pim\Enum\TypeFiche;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class OcrCoreTest extends TestCase
{
    public function testConsolidationUsesConfidenceThenFirstPageAndOffsetsReferences(): void
    {
        $result = (new OcrExtractionConsolidator())->consolidate([
            ['first_page' => 1, 'response' => ['answer' => ['fiche.label' => ['value' => 'Premier', 'confidence_score' => .8, 'references' => [['page' => 2]]]]]],
            ['first_page' => 6, 'response' => ['answer' => ['fiche.label' => ['value' => 'Second', 'confidence_score' => .9, 'references' => [['page' => 1]]], 'descGenerale' => ['value' => 'Texte', 'confidence_score' => .7, 'references' => [['page' => 3]]]]]],
        ]);
        self::assertSame('Texte', $result['suggestions'][0]['value']);
        self::assertSame([8], $result['suggestions'][0]['pages']);
        self::assertSame('Second', $result['suggestions'][1]['value']);
        self::assertSame([6], $result['suggestions'][1]['pages']);
        self::assertCount(2, $result['raw']['alternatives']['fiche.label']);
    }

    public function testConsolidationReadsBoxRootConfidenceAndReferenceMapsAndKeepsTables(): void
    {
        $result = (new OcrExtractionConsolidator())->consolidate([['first_page' => 6, 'response' => [
            'answer' => ['collections.salle' => [['nom' => 'Salon A', 'capaciteTheatre' => 80]]],
            'confidence_score' => ['collections.salle' => ['level' => 'HIGH', 'score' => .94]],
            'reference' => ['collections.salle' => [['location' => ['page_number' => 2]]]],
            'ai_agent_info' => ['processor' => 'enhanced_extract', 'models' => [['name' => 'gemini-2.5-pro']]],
        ]]]);
        self::assertSame([['nom' => 'Salon A', 'capaciteTheatre' => 80]], $result['suggestions'][0]['value']);
        self::assertSame(.94, $result['suggestions'][0]['confidence']);
        self::assertSame([7], $result['suggestions'][0]['pages']);
        self::assertSame('enhanced_extract', $result['agent']);
        self::assertSame('gemini-2.5-pro', $result['model']);
    }

    public function testSavedDecisionIsImmutableAndReviewStatusTracksPendingRows(): void
    {
        $fiche = new Fiche(TypeFiche::Lieu);
        $asset = new MediaAsset(new Ulid(), 'private/test.pdf', 'test.pdf', 'application/pdf', 123, str_repeat('a', 64), MediaKind::Document);
        $extraction = new DocumentExtraction($fiche, $asset, 'PJ_PLAN_GENERAL', ['version' => 1, 'fields' => []], 'actor');
        $first = new OcrSuggestion($extraction, 'fiche.label', 'Libellé', 'string', 'Nouveau', 'Ancien', .9, [1]);
        new OcrSuggestion($extraction, 'descGenerale', 'Description', 'string', 'Texte', null, .8, [2]);
        $extraction->complete([], 'enhanced', 'model');
        $first->decide(SuggestionStatus::Accepted, 'validator');
        $extraction->refreshReviewStatus();
        self::assertSame(ExtractionStatus::PartiallyReviewed, $extraction->status());
        $this->expectException(\DomainException::class);
        $first->correct('Autre');
    }
}
