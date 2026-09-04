<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Geo\ZonesGeographiques;
use PHPUnit\Framework\TestCase;

/** Référentiel pays → régions → départements des zones mobiles, et résolution des libellés historiques. */
final class ZonesGeographiquesTest extends TestCase
{
    public function testLeReferentielEstCoherent(): void
    {
        $pays = ZonesGeographiques::pays();
        self::assertSame('FR', $pays['France']);
        self::assertSame('BE', $pays['Belgique']);

        $regions = ZonesGeographiques::regions();
        self::assertSame('FR-IDF', $regions['Île-de-France']);
        self::assertSame('FR-PAC', $regions['Provence-Alpes-Côte d’Azur']);
        self::assertSame('BE-WAL', $regions['Région wallonne (Belgique)']);
        foreach (ZonesGeographiques::paysDesRegions() as $region => $codePays) {
            self::assertContains($codePays, $pays, $region);
        }
        $departements = ZonesGeographiques::departements();
        self::assertSame('FR-75', $departements['75 Paris']);
        self::assertSame('FR-2A', $departements['2A Corse-du-Sud']);
        self::assertCount(101, array_filter(ZonesGeographiques::regionsDesDepartements(), static fn (string $r): bool => str_starts_with($r, 'FR-')));
        foreach (ZonesGeographiques::regionsDesDepartements() as $departement => $region) {
            self::assertArrayHasKey($region, ZonesGeographiques::paysDesRegions(), $departement);
        }
    }

    public function testLesLibellesHistoriquesSeResolventEnCodes(): void
    {
        self::assertSame('FR', ZonesGeographiques::resoudrePays('France'));
        self::assertSame('MC', ZonesGeographiques::resoudrePays('Monaco'));
        self::assertSame('BE', ZonesGeographiques::resoudrePays('be'));
        self::assertNull(ZonesGeographiques::resoudrePays('Atlantide'));

        self::assertSame('FR-IDF', ZonesGeographiques::resoudreRegion('Île-de-France'));
        self::assertSame('FR-IDF', ZonesGeographiques::resoudreRegion('ile de france'));
        self::assertSame('FR-PAC', ZonesGeographiques::resoudreRegion('Provence-Alpes-Côte-D’azur'));
        self::assertSame('FR-CVL', ZonesGeographiques::resoudreRegion('Centre-Val-de-Loire'));
        self::assertSame('FR-GES', ZonesGeographiques::resoudreRegion('Grand-Est'));
        self::assertSame('FR-PDL', ZonesGeographiques::resoudreRegion('Pays-de-la-Loire'));
        self::assertSame('FR-BRE', ZonesGeographiques::resoudreRegion('FR-BRE'));
        self::assertNull(ZonesGeographiques::resoudreRegion('Toute la France'));

        self::assertSame('FR-78', ZonesGeographiques::resoudreDepartement('Yvelines'));
        self::assertSame('FR-22', ZonesGeographiques::resoudreDepartement("Côtes d'Armor"));
        self::assertSame('FR-04', ZonesGeographiques::resoudreDepartement('Alpes de Haute-Provence'));
        self::assertSame('FR-21', ZonesGeographiques::resoudreDepartement("Côte d'Or"));
        self::assertSame('FR-75', ZonesGeographiques::resoudreDepartement('75 Paris'));
        self::assertSame('FR-2B', ZonesGeographiques::resoudreDepartement('2b'));
        self::assertNull(ZonesGeographiques::resoudreDepartement('Monaco'));
    }

    public function testLesAttributsStimulusPortentLesTablesDeDependance(): void
    {
        $attributs = ZonesGeographiques::attributsStimulus();
        self::assertSame('zones-geo', $attributs['data-controller']);
        $regions = json_decode($attributs['data-zones-geo-regions-value'], true, 512, JSON_THROW_ON_ERROR);
        $departements = json_decode($attributs['data-zones-geo-departements-value'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('FR', $regions['FR-IDF']);
        self::assertSame('FR-IDF', $departements['FR-75']);
    }
}
