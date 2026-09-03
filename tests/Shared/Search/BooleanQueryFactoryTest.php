<?php

declare(strict_types=1);

namespace App\Tests\Shared\Search;

use App\Shared\Search\BooleanQueryFactory;
use PHPUnit\Framework\TestCase;

final class BooleanQueryFactoryTest extends TestCase
{
    public function testChaqueMotDevientUnPrefixeObligatoire(): void
    {
        self::assertSame('+Grand* +Pavillon* +Chantilly*', BooleanQueryFactory::fromText('Grand Pavillon Chantilly'));
    }

    public function testLesMotsSousLaTailleDIndexationSontEcartes(): void
    {
        self::assertSame(['Grand', 'Pavillon', 'Chantilly'], BooleanQueryFactory::tokens('Le Grand Pavillon de Chantilly'));
    }

    public function testLesStopwordsInnoDbSontEcartesSansTenirCompteDeLaCasse(): void
    {
        // « The » n'est jamais indexé par InnoDB : l'exiger éliminerait la fiche.
        self::assertSame(
            ['Jeanne', 'Forest', 'Château', 'Montvillargenne'],
            BooleanQueryFactory::tokens('Jeanne & The Forest – Château de Montvillargenne'),
        );
        self::assertSame('+Originals* +Relais*', BooleanQueryFactory::fromText('THE Originals with Relais'));
    }

    public function testUneSaisieFaiteUniquementDeMotsNonIndexesDonneUneRequeteVide(): void
    {
        self::assertSame('', BooleanQueryFactory::fromText('the la'));
        self::assertSame('%the la%', BooleanQueryFactory::likePattern('the la'));
    }
}
