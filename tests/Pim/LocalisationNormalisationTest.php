<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\Localisation;
use PHPUnit\Framework\TestCase;

final class LocalisationNormalisationTest extends TestCase
{
    public function testLEntiteNormaliseRegionEtDepartement(): void
    {
        $localisation = new Localisation();
        $localisation->changeRegion('Île-De-France');
        $localisation->changeDepartement('val d oise');

        self::assertSame('Île-de-France', $localisation->region());
        self::assertSame("Val-d'Oise", $localisation->departement());
    }

    public function testLeCodePostalFrancaisEstRepare(): void
    {
        $localisation = new Localisation();
        $localisation->changePays('France');
        $localisation->changeCodePostal('6130');

        self::assertSame('06130', $localisation->codePostal());
    }

    public function testLeCodePostalEtrangerNEstPasTouche(): void
    {
        // Les CP belges et suisses à 4 chiffres sont légitimes.
        $localisation = new Localisation();
        $localisation->changePays('Belgique');
        $localisation->changeCodePostal('1000');

        self::assertSame('1000', $localisation->codePostal());
    }

    public function testSansPaysLeCodePostalResteIntact(): void
    {
        $localisation = new Localisation();
        $localisation->changeCodePostal('6130');

        self::assertSame('6130', $localisation->codePostal());
    }
}
