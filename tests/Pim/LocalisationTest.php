<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\Localisation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LocalisationTest extends TestCase
{
    public function testAddressPreservesPostalCodeAndCoordinatesPrecision(): void
    {
        $localisation = new Localisation();
        $localisation->changePays(' France ');
        $localisation->changeCodePostal('06000');
        $localisation->changeVille(' Nice ');
        $localisation->changeLatitude('43.710172800000000');
        $localisation->changeLongitude('7.261953200000000');

        self::assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', $localisation->id());
        self::assertSame('France', $localisation->pays());
        self::assertSame('06000', $localisation->codePostal());
        self::assertSame('Nice', $localisation->ville());
        self::assertSame('43.710172800000000', $localisation->latitude());
        self::assertSame('7.261953200000000', $localisation->longitude());
    }

    #[DataProvider('invalidCoordinates')]
    public function testCoordinatesOutsideTheirBoundsAreRejected(string $coordinate, string $value): void
    {
        $localisation = new Localisation();

        $this->expectException(\InvalidArgumentException::class);

        if ('latitude' === $coordinate) {
            $localisation->changeLatitude($value);

            return;
        }

        $localisation->changeLongitude($value);
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidCoordinates(): iterable
    {
        yield 'latitude too low' => ['latitude', '-90.000000000000001'];
        yield 'latitude too high' => ['latitude', '90.000000000000001'];
        yield 'longitude too low' => ['longitude', '-180.000000000000001'];
        yield 'longitude too high' => ['longitude', '180.000000000000001'];
        yield 'not numeric' => ['latitude', 'north'];
    }
}
