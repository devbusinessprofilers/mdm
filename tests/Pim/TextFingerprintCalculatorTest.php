<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Service\TextFingerprintCalculator;
use PHPUnit\Framework\TestCase;

final class TextFingerprintCalculatorTest extends TestCase
{
    public function testNormalizeIgnoresCaseAccentsAndPunctuation(): void
    {
        $calculator = new TextFingerprintCalculator();

        self::assertSame('un cadre elegant au chateau', $calculator->normalize('Un Cadre Élégant, au Château !'));
        self::assertSame('salle de reunion', $calculator->normalize('  Salle   de   Réunion  '));
    }

    public function testExactHashMatchesForTextsThatOnlyDifferByFormatting(): void
    {
        $calculator = new TextFingerprintCalculator();

        $left = $calculator->exactHash($calculator->normalize('Hôtel de charme, 40 chambres.'));
        $right = $calculator->exactHash($calculator->normalize('HÔTEL de CHARME — 40 chambres'));

        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $left);
        self::assertSame($left, $right);
    }

    public function testSimhashIsAStable64BitHash(): void
    {
        $calculator = new TextFingerprintCalculator();
        $normalized = $calculator->normalize('Un vaste domaine viticole propose seminaires et team building en pleine nature.');

        $hash = $calculator->simhash($normalized);

        self::assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $hash);
        self::assertSame($hash, $calculator->simhash($normalized));
    }

    public function testSimhashStaysCloseForNearDuplicatesAndFarForUnrelatedTexts(): void
    {
        $calculator = new TextFingerprintCalculator();
        $base = 'Un vaste domaine viticole propose des seminaires et du team building en pleine nature avec hebergement.';
        $tweaked = 'Un vaste domaine viticole propose des seminaires et du team building en pleine nature avec restauration.';
        $unrelated = 'Restaurant gastronomique parisien specialise dans la cuisine japonaise et les sushis frais du marche.';

        $hBase = $calculator->simhash($calculator->normalize($base));
        $hTweaked = $calculator->simhash($calculator->normalize($tweaked));
        $hUnrelated = $calculator->simhash($calculator->normalize($unrelated));

        // Copie quasi-verbatim (un mot changé) proche ; texte sans rapport très
        // loin — le seuil par défaut (10) sépare nettement les deux.
        self::assertLessThanOrEqual(10, $calculator->distance($hBase, $hTweaked));
        self::assertGreaterThanOrEqual(20, $calculator->distance($hBase, $hUnrelated));
    }

    public function testLengthCountsNormalizedCharacters(): void
    {
        $calculator = new TextFingerprintCalculator();

        // "bonjour a tous" = 14 caractères une fois normalisé.
        self::assertSame(14, $calculator->length($calculator->normalize('  Bonjour à  tous ')));
    }
}
