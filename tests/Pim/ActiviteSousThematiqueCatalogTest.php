<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Lov\ActiviteLovCatalog;
use PHPUnit\Framework\TestCase;

final class ActiviteSousThematiqueCatalogTest extends TestCase
{
    public function testEverySousThematiqueBelongsToAKnownThematique(): void
    {
        $sousThematiques = ActiviteLovCatalog::allChoices()['SOUS_THEMATIQUE_ACTIVITE'];
        self::assertCount(64, $sousThematiques);
        $thematiques = array_keys(ActiviteLovCatalog::allChoices()['THEMATIQUE_ACTIVITE']);
        foreach (array_keys($sousThematiques) as $code) {
            self::assertContains(ActiviteLovCatalog::parentOf($code), $thematiques, $code);
        }
    }

    public function testSousThematiquesForFiltersByParent(): void
    {
        $sportives = ActiviteLovCatalog::sousThematiquesFor('TA_SPORTIVE_LUDIQUE');
        self::assertCount(12, $sportives);
        self::assertSame('Olympiades', $sportives['TA_SPORTIVE_LUDIQUE_SS_1']);
        self::assertCount(4, ActiviteLovCatalog::sousThematiquesFor('TA_DIGITAL_HIGH_TECH'));
    }

    public function testParentOfRejectsUnknownCode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ActiviteLovCatalog::parentOf('TA_INCONNU_SS_1');
    }
}
