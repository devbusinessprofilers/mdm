<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Lov\ServiceLovCatalog;
use PHPUnit\Framework\TestCase;

final class ServiceSousPrestationCatalogTest extends TestCase
{
    public function testEverySousPrestationBelongsToAKnownPrestation(): void
    {
        $sousPrestations = ServiceLovCatalog::sousPrestations();
        self::assertCount(65, $sousPrestations);
        $prestations = array_keys(ServiceLovCatalog::prestations());
        foreach (array_keys(ServiceLovCatalog::sousPrestationAttributes()) as $attribute) {
            self::assertContains(ServiceLovCatalog::familleOf($attribute), $prestations, $attribute);
        }
        foreach (array_keys($sousPrestations) as $code) {
            self::assertContains(ServiceLovCatalog::parentOf($code), $prestations, $code);
        }
    }

    public function testValueIdsRoundTrip(): void
    {
        $ids = ServiceLovCatalog::sousPrestationValueIds('TS_ANIMATION_ARTISTE_SS', ['TS_ANIMATION_ARTISTE_SS_10']);
        self::assertCount(1, $ids);
        self::assertSame('TS_ANIMATION_ARTISTE_SS_10', ServiceLovCatalog::sousPrestationValueCode('TS_ANIMATION_ARTISTE_SS', $ids[0]));
    }

    public function testParentOfRejectsCodeWithoutSuffix(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ServiceLovCatalog::parentOf('TS_TRAITEUR');
    }
}
