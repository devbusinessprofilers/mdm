<?php

declare(strict_types=1);

namespace App\Tests\Pim\Import\Legacy;

use App\Pim\Import\Legacy\LegacyTranslationRule;
use PHPUnit\Framework\TestCase;

final class LegacyTranslationRuleTest extends TestCase
{
    public function testIdenticalSourcesAreAvailable(): void
    {
        self::assertSame(LegacyTranslationRule::AVAILABLE, LegacyTranslationRule::decide('Texte identique.', 'Texte identique.'));
        self::assertSame(LegacyTranslationRule::AVAILABLE, LegacyTranslationRule::decide("  Texte identique.\n", 'Texte identique.'));
    }

    public function testImportTruncationIsStillAvailable(): void
    {
        $legacy = str_repeat('a', 1200);
        $pim = mb_substr($legacy, 0, 999).'…';
        self::assertSame(LegacyTranslationRule::AVAILABLE, LegacyTranslationRule::decide($pim, $legacy));
    }

    public function testDivergentSourcesAreObsolete(): void
    {
        self::assertSame(LegacyTranslationRule::OBSOLETE, LegacyTranslationRule::decide('Nouveau texte.', 'Ancien texte.'));
        self::assertSame(LegacyTranslationRule::OBSOLETE, LegacyTranslationRule::decide('Texte.', null));
    }

    public function testEmptyPimSourceIsSkipped(): void
    {
        self::assertSame(LegacyTranslationRule::SKIP, LegacyTranslationRule::decide(null, 'Texte legacy.'));
        self::assertSame(LegacyTranslationRule::SKIP, LegacyTranslationRule::decide('  ', 'Texte legacy.'));
    }
}
