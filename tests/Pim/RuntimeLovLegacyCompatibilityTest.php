<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Lov\ActiviteLovCatalog;
use App\Pim\Lov\LieuLovCatalog;
use App\Pim\Lov\LovRuntimeCatalog;
use App\Pim\Lov\RestaurantLovCatalog;
use App\Pim\Lov\ServiceLovCatalog;
use PHPUnit\Framework\TestCase;

final class RuntimeLovLegacyCompatibilityTest extends TestCase
{
    public function testLegacyStableIdsRemainReadableAfterDatabaseLovActivation(): void
    {
        $valuesProperty = new \ReflectionProperty(LovRuntimeCatalog::class, 'values');
        $idsProperty = new \ReflectionProperty(LovRuntimeCatalog::class, 'ids');
        $previousValues = $valuesProperty->getValue();
        $previousIds = $idsProperty->getValue();

        try {
            $valuesProperty->setValue(null, [
                'GENERALE_TYPOLOGIE' => ['GENERALE_TYPOLOGIE_1' => ['id' => 1, 'label' => 'Hôtel 2 étoiles', 'active' => true]],
                'THEMATIQUE_ACTIVITE' => ['TA_SPORTIVE_LUDIQUE' => ['id' => 2, 'label' => 'Sportives & Ludiques', 'active' => true]],
                'TYPE_CUISINE' => ['FRANCAIS' => ['id' => 3, 'label' => 'Français', 'active' => true]],
                'TYPE_PRESTATAIRE' => ['TS_ANIMATION_ARTISTE' => ['id' => 4, 'label' => 'Animations & Artistes', 'active' => true]],
            ]);
            $idsProperty->setValue(null, [
                1 => ['attribute' => 'GENERALE_TYPOLOGIE', 'code' => 'GENERALE_TYPOLOGIE_1'],
                2 => ['attribute' => 'THEMATIQUE_ACTIVITE', 'code' => 'TA_SPORTIVE_LUDIQUE'],
                3 => ['attribute' => 'TYPE_CUISINE', 'code' => 'FRANCAIS'],
                4 => ['attribute' => 'TYPE_PRESTATAIRE', 'code' => 'TS_ANIMATION_ARTISTE'],
            ]);

            self::assertSame('GENERALE_TYPOLOGIE_1', LieuLovCatalog::valueCode(self::legacyLieuId('GENERALE_TYPOLOGIE', 'GENERALE_TYPOLOGIE_1')));
            self::assertSame('TA_SPORTIVE_LUDIQUE', ActiviteLovCatalog::valueCode('THEMATIQUE_ACTIVITE', self::legacyId('THEMATIQUE_ACTIVITE', 'TA_SPORTIVE_LUDIQUE')));
            self::assertSame('FRANCAIS', RestaurantLovCatalog::valueCode('TYPE_CUISINE', self::legacyId('TYPE_CUISINE', 'FRANCAIS')));
            self::assertSame('TS_ANIMATION_ARTISTE', ServiceLovCatalog::valueCode(self::legacyId('TYPE_PRESTATAIRE', 'TS_ANIMATION_ARTISTE')));

            $restaurant = new Restaurant();
            $restaurant->fiche()->replaceAttributeValues('TYPE_CUISINE', [self::legacyId('TYPE_CUISINE', 'FRANCAIS')]);
            self::assertSame(['FRANCAIS'], $restaurant->typesCuisine());
        } finally {
            $valuesProperty->setValue(null, $previousValues);
            $idsProperty->setValue(null, $previousIds);
        }
    }

    private static function legacyId(string $attribute, string $code): int
    {
        return (int) sprintf('%u', crc32('value:'.$attribute.':'.$code));
    }

    private static function legacyLieuId(string $attribute, string $code): int
    {
        $unpacked = unpack('Nid', substr(hash('sha256', 'value:'.$attribute.':'.$code, true), 0, 4));
        self::assertIsArray($unpacked);

        return $unpacked['id'] & 0x7FFFFFFF;
    }
}
