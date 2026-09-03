<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Enum\TypeFiche;
use PHPUnit\Framework\TestCase;

final class TypeFicheTest extends TestCase
{
    public function testLeSegmentDUrlEstReversible(): void
    {
        foreach (TypeFiche::cases() as $type) {
            self::assertSame($type, TypeFiche::depuisSlug($type->slug()), $type->value);
        }
        self::assertNull(TypeFiche::depuisSlug('inconnu'));
        self::assertSame('services', TypeFiche::ServiceEvenementiel->slug());
    }

    public function testLesPlateauxRepasSontHorsPerimetre(): void
    {
        self::assertFalse(TypeFiche::Traiteur->estOperationnel());
        self::assertNull(TypeFiche::Traiteur->classeDetail());
        self::assertSame(Lieu::class, TypeFiche::Lieu->classeDetail());
        self::assertSame([TypeFiche::Lieu, TypeFiche::Restaurant, TypeFiche::Activite, TypeFiche::ServiceEvenementiel], TypeFiche::operationnels());
    }

    public function testLesLibellesSontAccentues(): void
    {
        self::assertSame('Service événementiel', TypeFiche::ServiceEvenementiel->libelle());
        self::assertSame('Activités', TypeFiche::Activite->libellePluriel());
    }
}
