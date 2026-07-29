<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Lov\LieuLovCatalog;
use PHPUnit\Framework\TestCase;

final class LieuLovCatalogTest extends TestCase
{
    public function testGeneratedCodesKeepTheFrenchLabelsFromTheWorkbook(): void
    {
        $typologies = LieuLovCatalog::choicesFor('GENERALE_TYPOLOGIE');
        $statuses = LieuLovCatalog::choicesFor('MICE_STATUT');

        self::assertCount(40, $typologies);
        self::assertSame('Hôtel 2 étoiles', $typologies['GENERALE_TYPOLOGIE_1']);
        self::assertSame('Autres Types de Lieux', $typologies['GENERALE_TYPOLOGIE_40']);
        self::assertSame('Premium', $statuses['MICE_STATUT_4']);
    }

    public function testUnknownAttributeReturnsNoChoices(): void
    {
        self::assertSame([], LieuLovCatalog::choicesFor('UNKNOWN'));
    }

    public function testValueIdsAreGloballyUniqueAcrossAttributes(): void
    {
        /** @var array<int, array{attribute: string, value: string}> $seen */
        $seen = [];

        foreach (LieuLovCatalog::allChoices() as $attributeCode => $choices) {
            foreach ($choices as $valueCode => $_label) {
                $valueId = LieuLovCatalog::valueId($attributeCode, $valueCode);
                if (isset($seen[$valueId])) {
                    self::fail(sprintf(
                        'LOV value id %d is shared by %s/%s and %s/%s.',
                        $valueId,
                        $seen[$valueId]['attribute'],
                        $seen[$valueId]['value'],
                        $attributeCode,
                        $valueCode,
                    ));
                }

                $seen[$valueId] = ['attribute' => $attributeCode, 'value' => $valueCode];
            }
        }

        self::assertNotEmpty($seen);
    }
}
