<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Service\LovValeurResolution;
use PHPUnit\Framework\TestCase;

final class LovValeurResolutionTest extends TestCase
{
    private const CHOIX = [
        'TYPE_CUISINE_35' => 'Fruits de mer',
        'TYPE_CUISINE_36' => 'Français',
        'TYPE_CUISINE_47' => 'Méditerranéen',
    ];

    public function testToutesLesFormesAboutissentAuMemeCode(): void
    {
        foreach (['FRUITS_DE_MER', 'FRUIT DE MER', 'Fruit De Mer', 'fruits de mer', 'Fruits de mer'] as $candidat) {
            self::assertSame('TYPE_CUISINE_35', LovValeurResolution::codePour(self::CHOIX, $candidat), $candidat);
        }
    }

    public function testUnCodeValideEstConserve(): void
    {
        self::assertSame('TYPE_CUISINE_36', LovValeurResolution::codePour(self::CHOIX, 'TYPE_CUISINE_36'));
    }

    public function testLesAccentsSontIgnores(): void
    {
        self::assertSame('TYPE_CUISINE_36', LovValeurResolution::codePour(self::CHOIX, 'FRANCAIS'));
        self::assertSame('TYPE_CUISINE_47', LovValeurResolution::codePour(self::CHOIX, 'MEDITERRANEEN'));
    }

    public function testUnCandidatInconnuOuVideNeResoutRien(): void
    {
        self::assertNull(LovValeurResolution::codePour(self::CHOIX, 'CUISINE_MARTIENNE'));
        self::assertNull(LovValeurResolution::codePour(self::CHOIX, '  '));
    }
}
