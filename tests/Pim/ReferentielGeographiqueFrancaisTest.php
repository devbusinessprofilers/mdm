<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Service\ReferentielGeographiqueFrancais;
use PHPUnit\Framework\TestCase;

final class ReferentielGeographiqueFrancaisTest extends TestCase
{
    public function testNormaliseLesVariantesDeRegion(): void
    {
        self::assertSame('Île-de-France', ReferentielGeographiqueFrancais::normaliserRegion('Île-De-France'));
        self::assertSame('Île-de-France', ReferentielGeographiqueFrancais::normaliserRegion('ile de france'));
        self::assertSame('Île-de-France', ReferentielGeographiqueFrancais::normaliserRegion('ILE-DE-FRANCE'));
        self::assertSame('Hauts-de-France', ReferentielGeographiqueFrancais::normaliserRegion('Hauts-De-France'));
        self::assertSame("Provence-Alpes-Côte d'Azur", ReferentielGeographiqueFrancais::normaliserRegion('Provence-Alpes-Côte-D’Azur'));
        self::assertSame('Pays de la Loire', ReferentielGeographiqueFrancais::normaliserRegion('Pays-De-La-Loire'));
        self::assertSame('Centre-Val de Loire', ReferentielGeographiqueFrancais::normaliserRegion('Centre-Val-De-Loire'));
        self::assertSame('Grand Est', ReferentielGeographiqueFrancais::normaliserRegion('Grand-Est'));
    }

    public function testLaisseIntactesLesValeursHorsReferentiel(): void
    {
        self::assertSame('Bayern', ReferentielGeographiqueFrancais::normaliserRegion('Bayern'));
        self::assertSame('Monaco', ReferentielGeographiqueFrancais::normaliserRegion('Monaco'));
        self::assertSame('Comunidad de Madrid', ReferentielGeographiqueFrancais::normaliserDepartement('Comunidad de Madrid'));
    }

    public function testNormaliseLesDepartements(): void
    {
        self::assertSame("Val-d'Oise", ReferentielGeographiqueFrancais::normaliserDepartement('val d oise'));
        self::assertSame('Alpes-Maritimes', ReferentielGeographiqueFrancais::normaliserDepartement('ALPES MARITIMES'));
        self::assertSame("Côte-d'Or", ReferentielGeographiqueFrancais::normaliserDepartement('cote d’or'));
        self::assertSame('06', ReferentielGeographiqueFrancais::numeroDepartement('Alpes-Maritimes'));
        self::assertSame('2A', ReferentielGeographiqueFrancais::numeroDepartement('Corse-du-Sud'));
        self::assertSame('974', ReferentielGeographiqueFrancais::numeroDepartement('La Réunion'));
        self::assertNull(ReferentielGeographiqueFrancais::numeroDepartement('Bayern'));
    }

    public function testRepareLeZeroInitialDesCodesPostaux(): void
    {
        self::assertSame('06130', ReferentielGeographiqueFrancais::reparerCodePostal('6130'));
        self::assertSame('06414', ReferentielGeographiqueFrancais::reparerCodePostal('06414'));
        self::assertSame('75008', ReferentielGeographiqueFrancais::reparerCodePostal('75008'));
        // Trois chiffres ou moins : pas un CP français réparable.
        self::assertSame('130', ReferentielGeographiqueFrancais::reparerCodePostal('130'));
    }

    public function testControleLaCoherenceCodePostalDepartement(): void
    {
        self::assertTrue(ReferentielGeographiqueFrancais::codePostalCoherent('06130', 'Alpes-Maritimes'));
        self::assertFalse(ReferentielGeographiqueFrancais::codePostalCoherent('75008', 'Alpes-Maritimes'));
        self::assertTrue(ReferentielGeographiqueFrancais::codePostalCoherent('20090', 'Corse-du-Sud'));
        self::assertTrue(ReferentielGeographiqueFrancais::codePostalCoherent('97400', 'La Réunion'));
        self::assertNull(ReferentielGeographiqueFrancais::codePostalCoherent('06130', 'Bayern'));
        self::assertNull(ReferentielGeographiqueFrancais::codePostalCoherent('613', 'Alpes-Maritimes'));
    }
}
