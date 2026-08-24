<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Service\DataTourisme\DataTourismeFluxReader;
use PHPUnit\Framework\TestCase;

final class DataTourismeFluxReaderTest extends TestCase
{
    public function testMappeUnObjetJsonLd(): void
    {
        $poi = DataTourismeFluxReader::mapper([
            '@type' => ['schema:LodgingBusiness'],
            'rdfs:label' => ['fr' => ['Hôtel du Test']],
            'hasDescription' => [['dc:description' => ['fr' => ['Un hôtel de charme.']]]],
            'isLocatedAt' => [[
                'schema:address' => [['schema:postalCode' => '37000', 'schema:addressLocality' => ['Tours']]],
                'schema:geo' => ['schema:latitude' => '47.39', 'schema:longitude' => '0.68'],
            ]],
            'hasFeature' => [
                ['rdfs:label' => ['fr' => ['Piscine']]],
                ['rdfs:label' => ['fr' => ['Spa']]],
            ],
        ]);

        self::assertNotNull($poi);
        self::assertSame('Hôtel du Test', $poi->nom);
        self::assertSame('37000', $poi->codePostal);
        self::assertSame('Tours', $poi->ville);
        self::assertSame('47.39', $poi->latitude);
        self::assertSame('0.68', $poi->longitude);
        self::assertSame('Un hôtel de charme.', $poi->description);
        self::assertSame(['piscine', 'spa'], $poi->features);
    }

    public function testAccepteLaFormeLanguageValue(): void
    {
        $poi = DataTourismeFluxReader::mapper([
            'rdfs:label' => ['@language' => 'fr', '@value' => 'Musée'],
        ]);

        self::assertNotNull($poi);
        self::assertSame('Musée', $poi->nom);
    }

    public function testRejetteUnObjetSansNom(): void
    {
        self::assertNull(DataTourismeFluxReader::mapper(['isLocatedAt' => []]));
    }
}
