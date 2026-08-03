<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Attribute\CompletenessTarget;
use App\Pim\Completeness\CompletenessFieldCatalog;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Enum\TypeFiche;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CompletenessFieldCatalogTest extends TestCase
{
    #[DataProvider('catalogSizes')]
    public function testEverySupportedDomainHasAStableFieldCatalog(TypeFiche $type, int $minimum): void
    {
        $definitions = (new CompletenessFieldCatalog())->definitions($type);
        self::assertGreaterThanOrEqual($minimum, count($definitions));
        self::assertCount(count($definitions), array_unique(array_map(static fn ($definition): string => $definition->code, $definitions)));
    }

    /** @return iterable<string, array{TypeFiche, int}> */
    public static function catalogSizes(): iterable
    {
        yield 'lieu' => [TypeFiche::Lieu, 100];
        yield 'activite' => [TypeFiche::Activite, 25];
        yield 'restaurant' => [TypeFiche::Restaurant, 45];
        yield 'service' => [TypeFiche::ServiceEvenementiel, 25];
    }

    public function testDefinitionsAndCodeIndexAreReused(): void
    {
        $catalog = new CompletenessFieldCatalog();
        $definitions = $catalog->definitions(TypeFiche::Lieu);

        self::assertSame($definitions[0], $catalog->definitions(TypeFiche::Lieu)[0]);
        self::assertSame($definitions[0], $catalog->find(TypeFiche::Lieu, $definitions[0]->code));
    }

    /** @param class-string $class */
    #[DataProvider('lengthTargets')]
    public function testEntityTargetMatchesDoctrineLength(string $class, string $property, int $expected): void
    {
        $reflection = new \ReflectionProperty($class, $property);
        $target = $reflection->getAttributes(CompletenessTarget::class)[0]->newInstance();
        $column = $reflection->getAttributes(\Doctrine\ORM\Mapping\Column::class)[0]->newInstance();

        self::assertSame($expected, $target->length);
        self::assertSame($expected, $column->length);
    }

    /** @return iterable<string, array{class-string, string, int}> */
    public static function lengthTargets(): iterable
    {
        yield 'site lieu' => [Lieu::class, 'generaleWebsiteUrl', 100];
        yield 'details PMR' => [Lieu::class, 'pmrDetails', 150];
        yield 'description lieu' => [Lieu::class, 'descGenerale', 1000];
        yield 'description hebergement' => [Lieu::class, 'chambreDescGenerale', 1000];
        yield 'atout lieu' => [Lieu::class, 'atout1', 35];
        yield 'site restaurant' => [Restaurant::class, 'siteOfficiel', 100];
    }
}
