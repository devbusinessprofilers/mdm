<?php

declare(strict_types=1);

namespace App\Tests\Vision;

use App\Dam\Entity\MediaAsset;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Enum\TypeFiche;
use App\Shared\Enum\DecisionStatus;
use App\Vision\Entity\ImageEnhancement;
use App\Vision\Entity\ImageRecognition;
use App\Vision\Entity\ImageRecognitionSuggestion;
use App\Vision\Enum\EnhancementStatus;
use App\Vision\Enum\RecognitionStatus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class VisionCoreTest extends TestCase
{
    public function testEnhancementLifecycleFromQueuedToAccepted(): void
    {
        $enhancement = $this->enhancement();
        self::assertSame(EnhancementStatus::Queued, $enhancement->status());
        $enhancement->start();
        self::assertSame(EnhancementStatus::Processing, $enhancement->status());
        self::assertSame(1, $enhancement->attempts());
        $enhancement->start();
        self::assertSame(1, $enhancement->attempts());
        $enhancement->complete('dev/fiche/asset/retouche/x.png', str_repeat('b', 64), 456, ['size' => '1024x1024']);
        self::assertSame(EnhancementStatus::Ready, $enhancement->status());
        $enhancement->accept('validator');
        self::assertSame(EnhancementStatus::Accepted, $enhancement->status());
        self::assertSame('validator', $enhancement->decidedBy());
    }

    public function testEnhancementDecisionsRequireReadyAndRequeueRequiresFailed(): void
    {
        $enhancement = $this->enhancement();
        try {
            $enhancement->accept('validator');
            self::fail('Une retouche en file ne doit pas être acceptable.');
        } catch (\DomainException) {
        }
        try {
            $enhancement->reject('validator');
            self::fail('Une retouche en file ne doit pas être rejetable.');
        } catch (\DomainException) {
        }
        try {
            $enhancement->requeue();
            self::fail('Seule une retouche en échec doit être relançable.');
        } catch (\DomainException) {
        }
        $enhancement->start();
        $enhancement->fail('Appel fournisseur en erreur.');
        self::assertSame(EnhancementStatus::Failed, $enhancement->status());
        $enhancement->requeue();
        self::assertSame(EnhancementStatus::Queued, $enhancement->status());
        self::assertNull($enhancement->errorMessage());
    }

    public function testMediaSourceFallsBackToOriginalAndRevertRestoresIt(): void
    {
        $media = $this->media();
        self::assertSame('dev/fiche/asset/original.jpg', $media->sourceStorageKey());
        self::assertSame(str_repeat('a', 64), $media->sourceChecksum());
        self::assertFalse($media->isEnhanced());

        $media->applyEnhancedSource('dev/fiche/asset/retouche/x.png', str_repeat('b', 64));
        self::assertTrue($media->isEnhanced());
        self::assertSame('dev/fiche/asset/retouche/x.png', $media->sourceStorageKey());
        self::assertSame(str_repeat('b', 64), $media->sourceChecksum());
        self::assertSame('dev/fiche/asset/original.jpg', $media->originalStorageKey());
        self::assertSame(str_repeat('a', 64), $media->checksum());

        $media->revertToOriginal();
        self::assertFalse($media->isEnhanced());
        self::assertSame('dev/fiche/asset/original.jpg', $media->sourceStorageKey());
        self::assertSame(str_repeat('a', 64), $media->sourceChecksum());
    }

    public function testRecognitionReviewStatusTracksPendingSuggestionsAndDecisionsAreImmutable(): void
    {
        $fiche = new Fiche(TypeFiche::Lieu);
        $recognition = new ImageRecognition($fiche, $this->media(), new RessourceLieu(), 'Décris cette photo.', 'gpt-4o', ImageRecognition::CREATED_BY_AUTO);
        $recognition->start();
        $legende = new ImageRecognitionSuggestion($recognition, ImageRecognitionSuggestion::PATH_LEGENDE, 'Légende', 'Salle de réunion lumineuse.', null);
        new ImageRecognitionSuggestion($recognition, ImageRecognitionSuggestion::PATH_KEYWORDS, 'Mots-clés', ['salle', 'réunion'], 'ancien mot');
        $recognition->complete(['model' => 'gpt-4o']);
        self::assertSame(RecognitionStatus::Ready, $recognition->status());

        $legende->correct('Salle de réunion baignée de lumière.');
        $legende->decide(DecisionStatus::Accepted, 'validator');
        $recognition->refreshReviewStatus();
        self::assertSame(RecognitionStatus::PartiallyReviewed, $recognition->status());

        foreach ($recognition->suggestions() as $suggestion) {
            if ($suggestion->isPending()) {
                $suggestion->decide(DecisionStatus::Rejected, 'validator');
            }
        }
        $recognition->refreshReviewStatus();
        self::assertSame(RecognitionStatus::Reviewed, $recognition->status());

        $this->expectException(\DomainException::class);
        $legende->correct('Encore une autre légende.');
    }

    private function enhancement(): ImageEnhancement
    {
        return new ImageEnhancement(new Fiche(TypeFiche::Lieu), $this->media(), new RessourceLieu(), 'Améliore la photo.', 'gpt-image-1', 'editor');
    }

    private function media(): MediaAsset
    {
        return new MediaAsset(new Ulid(), 'dev/fiche/asset/original.jpg', 'original.jpg', 'image/jpeg', 123, str_repeat('a', 64));
    }
}
