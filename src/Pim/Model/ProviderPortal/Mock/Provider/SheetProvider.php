<?php

namespace App\Pim\Model\ProviderPortal\Mock\Provider;

use App\Pim\Enum\ProviderPortal\SheetTypeEnum;
use App\Pim\Model\ProviderPortal\DTO\SheetDTO;

class SheetProvider
{
    public static function getSheet(string $slug): ?SheetDTO
    {
        foreach (self::findAll() as $sheet) {
            if ($sheet->slug === $slug) {
                return $sheet;
            }
        }

        return null;
    }

    /**
     * @return array<SheetDTO>
     */
    public static function findPlaceSheets(): array
    {
        return self::buildSheets(
            SheetTypeEnum::PLACE,
            [
                'sheet_place_001' => 'Le Grand Pavillon Chantilly',
                'sheet_place_002' => 'Château De Quesmy',
                'sheet_place_003' => 'Domaine du Château de Précy',
                'sheet_place_004' => 'Cabanes de La Réserve',
                'sheet_place_005' => 'Domaine du Colombier',
            ]
        );
    }

    /**
     * @return array<SheetDTO>
     */
    public static function findActivitySheets(): array
    {
        return self::buildSheets(
            SheetTypeEnum::ACTIVITY,
            [
                'sheet_activity_001' => 'Créativ\'Academy',
                'sheet_activity_002' => 'Murder Party - Enquête Policière',
                'sheet_activity_003' => 'Le Défi du Fort',
            ]
        );
    }

    /**
     * @return array<SheetDTO>
     */
    public static function findServiceSheets(): array
    {
        return self::buildSheets(
            SheetTypeEnum::SERVICE,
            [
                'sheet_service_001' => 'The Photographer',
                'sheet_service_002' => 'Gourmets de France',
                'sheet_service_003' => 'Solutions événements',
                'sheet_service_004' => 'France By Lilly',
            ]
        );
    }

    /**
     * @return array<SheetDTO>
     */
    public static function findRestaurantSheets(): array
    {
        return self::buildSheets(
            SheetTypeEnum::RESTAURANT,
            [
                'sheet_restaurant_001' => 'Le Bouchon Gourmand',
                'sheet_restaurant_002' => 'O Bistrot Chic',
                'sheet_restaurant_003' => 'Le Jardin d\'Hiver',
                'sheet_restaurant_004' => 'Le Sylvia',
            ]
        );
    }

    /**
     * @return array<SheetDTO>
     */
    public static function findMealTraySheets(): array
    {
        return self::buildSheets(
            SheetTypeEnum::MEAL_TRAY,
            [
                'sheet_meal_tray_001' => 'Madness traiteur',
                'sheet_meal_tray_002' => 'BTL Traiteur',
            ]
        );
    }

    /**
     * @return array<SheetDTO>
     */
    public static function findAll(): array
    {
        return array_merge(
            self::findPlaceSheets(),
            self::findActivitySheets(),
            self::findServiceSheets(),
            self::findRestaurantSheets(),
            self::findMealTraySheets(),
        );
    }

    private static function buildSheets(SheetTypeEnum $type, array $names): array
    {
        $result = [];
        foreach ($names as $id => $name) {
            $result[] = SheetDTO::mock($type, $name, $id);
        }

        return $result;
    }
}
