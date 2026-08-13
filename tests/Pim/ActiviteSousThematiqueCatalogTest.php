<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Lov\ActiviteLovCatalog;
use PHPUnit\Framework\TestCase;

final class ActiviteSousThematiqueCatalogTest extends TestCase
{
    public function testEverySousThematiqueAttributeBelongsToAKnownThematique(): void
    {
        $sousThematiques = ActiviteLovCatalog::sousThematiques();
        self::assertCount(64, $sousThematiques);
        $thematiques = array_keys(ActiviteLovCatalog::allChoices()['THEMATIQUE_ACTIVITE']);
        foreach (array_keys(ActiviteLovCatalog::sousThematiqueAttributes()) as $attribute) {
            self::assertContains(ActiviteLovCatalog::thematiqueOf($attribute), $thematiques, $attribute);
        }
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

    public function testParentOfRejectsCodeWithoutSuffix(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ActiviteLovCatalog::parentOf('TA_SPORTIVE_LUDIQUE');
    }
}
