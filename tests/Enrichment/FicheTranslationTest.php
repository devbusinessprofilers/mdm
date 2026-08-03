<?php

declare(strict_types=1);

namespace App\Tests\Enrichment;

use App\Enrichment\Entity\FicheTranslation;
use App\Enrichment\Enum\SupportedLocale;
use App\Enrichment\Enum\TranslationOrigin;
use App\Enrichment\Enum\TranslationStatus;
use App\Pim\Entity\Fiche;
use App\Pim\Enum\TypeFiche;
use PHPUnit\Framework\TestCase;

final class FicheTranslationTest extends TestCase
{
    public function testGoogleTranslationBecomesAvailable(): void
    {
        $translation = $this->translation();

        self::assertTrue($translation->schedule('Description', 'Texte français', 'request-1'));
        $translation->applyGoogle('French text', 'request-1');

        self::assertSame('French text', $translation->translatedText());
        self::assertSame(TranslationOrigin::Google, $translation->origin());
        self::assertSame(TranslationStatus::Available, $translation->status());
    }

    public function testAChangedSourcePreservesManualTextAndStoresGoogleSuggestion(): void
    {
        $translation = $this->translation();
        $translation->applyManual('Manual text', 'validator@example.test');

        self::assertTrue($translation->schedule('Description', 'Texte français modifié', 'request-2'));
        self::assertSame(TranslationStatus::Obsolete, $translation->status());
        $translation->applyGoogle('New Google text', 'request-2');

        self::assertSame('Manual text', $translation->translatedText());
        self::assertSame('New Google text', $translation->suggestedText());
        self::assertSame(TranslationOrigin::Manual, $translation->origin());
        self::assertSame(TranslationStatus::Obsolete, $translation->status());
    }

    public function testAnOutdatedGoogleResponseIsIgnored(): void
    {
        $translation = $this->translation();
        $translation->schedule('Description', 'Texte français', 'current-request');

        $translation->applyGoogle('Stale result', 'old-request');

        self::assertNull($translation->translatedText());
        self::assertSame(TranslationStatus::Pending, $translation->status());
    }

    private function translation(): FicheTranslation
    {
        return new FicheTranslation(
            new Fiche(TypeFiche::Lieu),
            'description',
            'Description',
            SupportedLocale::En,
            'Texte français',
        );
    }
}
