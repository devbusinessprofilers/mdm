<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\CritereGeo;
use App\Pim\Entity\Localisation;
use App\Pim\Entity\SiteDiffusion;
use App\Pim\Service\SiteDiffusionGeoAttribueur;
use PHPUnit\Framework\TestCase;

final class SiteDiffusionGeoAttribueurTest extends TestCase
{
    /** Tours ↔ Paris : ~205 km à vol d'oiseau ; la formule doit tomber à ±2 km. */
    public function testHaversineSurUneDistanceConnue(): void
    {
        $distance = SiteDiffusionGeoAttribueur::distanceKm(47.394144, 0.68484, 48.856614, 2.352222);
        self::assertEqualsWithDelta(205.0, $distance, 2.0);
        self::assertSame(0.0, SiteDiffusionGeoAttribueur::distanceKm(47.394144, 0.68484, 47.394144, 0.68484));
    }

    public function testCritereVilleDansEtHorsRayon(): void
    {
        $tours = self::site(CritereGeo::ville('Tours', '47.394144', '0.68484', 10));

        // Joué-lès-Tours : ~5 km du centre de Tours.
        self::assertTrue(SiteDiffusionGeoAttribueur::matche($tours, self::localisation('FR', latitude: '47.352', longitude: '0.663')));
        // Amboise : ~23 km.
        self::assertFalse(SiteDiffusionGeoAttribueur::matche($tours, self::localisation('FR', latitude: '47.413', longitude: '0.986')));
    }

    public function testFicheSansCoordonneesIgnoreeParUnCritereVille(): void
    {
        $tours = self::site(CritereGeo::ville('Tours', '47.394144', '0.68484', 10));

        self::assertFalse(SiteDiffusionGeoAttribueur::matche($tours, self::localisation('FR', ville: 'Tours')));
    }

    public function testCritereRegionNormaliseEtLimiteALaFrance(): void
    {
        $bretagne = self::site(CritereGeo::region('Bretagne'));
        $idf = self::site(CritereGeo::region('ile de france'));

        self::assertTrue(SiteDiffusionGeoAttribueur::matche($bretagne, self::localisation('FR', region: 'Bretagne')));
        self::assertTrue(SiteDiffusionGeoAttribueur::matche($idf, self::localisation('FR', region: 'Île-de-France')));
        // Même libellé hors de France : jamais matché.
        self::assertFalse(SiteDiffusionGeoAttribueur::matche($bretagne, self::localisation('BE', region: 'Bretagne')));
        self::assertFalse(SiteDiffusionGeoAttribueur::matche($bretagne, self::localisation('FR', region: 'Normandie')));
        self::assertFalse(SiteDiffusionGeoAttribueur::matche($bretagne, self::localisation('FR')));
    }

    public function testCritereDepartement(): void
    {
        $touraine = self::site(CritereGeo::departement('Indre-et-Loire'));

        self::assertTrue(SiteDiffusionGeoAttribueur::matche($touraine, self::localisation('FR', departement: 'indre et loire')));
        self::assertFalse(SiteDiffusionGeoAttribueur::matche($touraine, self::localisation('FR', departement: 'Loiret')));
    }

    public function testCriterePays(): void
    {
        $allemagne = self::site(CritereGeo::pays('de'));

        self::assertTrue(SiteDiffusionGeoAttribueur::matche($allemagne, self::localisation('DE')));
        self::assertFalse(SiteDiffusionGeoAttribueur::matche($allemagne, self::localisation('AT')));
    }

    public function testPlusieursCriteresEnOuLogique(): void
    {
        // Strasbourg / Colmar : deux villes sur le même site.
        $site = self::site(
            CritereGeo::ville('Strasbourg', '48.573405', '7.752111', 15),
            CritereGeo::ville('Colmar', '48.079359', '7.358512', 15),
        );

        self::assertTrue(SiteDiffusionGeoAttribueur::matche($site, self::localisation('FR', latitude: '48.58', longitude: '7.75')));
        self::assertTrue(SiteDiffusionGeoAttribueur::matche($site, self::localisation('FR', latitude: '48.08', longitude: '7.36')));
        // Mulhouse : hors des deux rayons.
        self::assertFalse(SiteDiffusionGeoAttribueur::matche($site, self::localisation('FR', latitude: '47.75', longitude: '7.34')));
    }

    public function testSiteSansCritereJamaisMatche(): void
    {
        self::assertFalse(SiteDiffusionGeoAttribueur::matche(self::site(), self::localisation('FR', latitude: '47.39', longitude: '0.68')));
    }

    public function testSerialisationCritereGeoTolerante(): void
    {
        $ville = CritereGeo::ville('Tours, Indre-et-Loire, France', '47.394144', '0.68484', 10);
        self::assertEquals($ville, CritereGeo::fromArray($ville->toArray()));

        $pays = CritereGeo::pays('FR');
        self::assertEquals($pays, CritereGeo::fromArray($pays->toArray()));

        self::assertNull(CritereGeo::fromArray([]));
        self::assertNull(CritereGeo::fromArray(['type' => 'inconnu']));
        self::assertNull(CritereGeo::fromArray(['type' => 'ville', 'ville' => 'Tours']));
        self::assertNull(CritereGeo::fromArray(['type' => 'ville', 'ville' => 'Tours', 'latitude' => '999', 'longitude' => '0.7', 'rayonKm' => 10]));
        self::assertNull(CritereGeo::fromArray(['type' => 'pays', 'countryCode' => 'France']));
    }

    public function testResumeDesCriteres(): void
    {
        self::assertSame('Tours + 10 km', CritereGeo::ville('Tours', '47.394144', '0.68484', 10)->resume());
        self::assertSame('Département Indre-et-Loire', CritereGeo::departement('indre et loire')->resume());
        self::assertSame('Région Bretagne', CritereGeo::region('Bretagne')->resume());
        self::assertSame('Pays Allemagne', CritereGeo::pays('DE')->resume());
    }

    private static function site(CritereGeo ...$criteres): SiteDiffusion
    {
        return new SiteDiffusion('test', 'Site de test', 'Tests', criteresGeo: array_values($criteres));
    }

    private static function localisation(
        string $countryCode,
        ?string $ville = null,
        ?string $region = null,
        ?string $departement = null,
        ?string $latitude = null,
        ?string $longitude = null,
    ): Localisation {
        $localisation = new Localisation();
        $localisation->changeCountryCode($countryCode);
        $localisation->changeVille($ville);
        $localisation->changeRegion($region);
        $localisation->changeDepartement($departement);
        $localisation->changeLatitude($latitude);
        $localisation->changeLongitude($longitude);

        return $localisation;
    }
}
