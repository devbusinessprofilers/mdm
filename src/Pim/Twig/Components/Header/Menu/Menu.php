<?php

namespace App\Pim\Twig\Components\Header\Menu;

use App\Pim\Enum\ProviderPortal\Twig\Component\Typography\TypographyTextColorEnum;
use App\Pim\Model\ProviderPortal\DTO\Menu\MenuDTO;
use App\Pim\Model\ProviderPortal\DTO\Menu\MenuDTOItem;
use App\Pim\Model\ProviderPortal\Mock\Provider\SheetProvider;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('Header:Menu', template: 'pim/components/Header/Menu/Menu.html.twig')]
class Menu
{
    public const DASHBOARD = 'dashboard';
    public const INVOICES = 'invoices';
    public const SHEETS = 'sheets';
    public const PERFORMANCES = 'performances';
    public const REVIEWS = 'reviews';
    public const COLLABORATORS = 'collaborators';

    public const PLACES = 'sheets.places';
    public const RESTAURANTS = 'sheets.restaurants';
    public const ACTIVITIES = 'sheets.activities';
    public const SERVICES = 'sheets.services';
    public const MEAL_TRAYS = 'sheets.meal_trays';

    /**
     * @var array<MenuDTOItem>
     */
    public array $items = [];

    public function __construct()
    {
        $menu = (new MenuDTO())
            ->addItem(new MenuDTOItem(self::DASHBOARD, 'menu.header.dashboard', 'provider_portal_chart_dashboard'))
            ->addItem(new MenuDTOItem(self::INVOICES, 'menu.header.invoices', 'homepage'))
            ->addItem(
                (new MenuDTOItem(self::SHEETS, 'menu.header.sheets'))
                    ->addItem((new MenuDTOItem(self::PLACES, 'menu.header.sheets.places'))
                        ->setIcon('building')
                        ->setIconColor(TypographyTextColorEnum::PRIMARY))
                    ->addItem((new MenuDTOItem(self::RESTAURANTS, 'menu.header.sheets.restaurants'))
                        ->setIcon('utensils')
                        ->setIconColor(TypographyTextColorEnum::PRIMARY))
                    ->addItem((new MenuDTOItem(self::ACTIVITIES, 'menu.header.sheets.activities'))
                        ->setIcon('biking')
                        ->setIconColor(TypographyTextColorEnum::PRIMARY))
                    ->addItem((new MenuDTOItem(self::SERVICES, 'menu.header.sheets.services'))
                        ->setIcon('call-bell')
                        ->setIconColor(TypographyTextColorEnum::PRIMARY))
                    ->addItem((new MenuDTOItem(self::MEAL_TRAYS, 'menu.header.sheets.meal_trays', 'homepage'))
                        ->setIcon('cookie')
                        ->setIconColor(TypographyTextColorEnum::PRIMARY))
            )
            ->addItem(new MenuDTOItem(self::PERFORMANCES, 'menu.header.performances', 'provider_portal_chart_analytics'))
            ->addItem(new MenuDTOItem(self::REVIEWS, 'menu.header.reviews', 'provider_portal_review_received'))
            ->addItem(new MenuDTOItem(self::COLLABORATORS, 'menu.header.collaborators', 'provider_portal_collaborator_list'));

        // Retrieve dynamic list of items
        $dynamicPlaceSubMenu = $menu->getItem(self::PLACES);
        if (null !== $dynamicPlaceSubMenu) {
            foreach (SheetProvider::findPlaceSheets() as $sheet) {
                $dynamicPlaceSubMenu
                    ->addItem(
                        (new MenuDTOItem($sheet->slug, $sheet->name, 'provider_portal_sheet_place_index'))
                            ->setRouteParameters(['slug' => $sheet->slug])
                    )
                ;
            }
        }

        $dynamicRestaurantSubMenu = $menu->getItem(self::RESTAURANTS);
        if (null !== $dynamicRestaurantSubMenu) {
            foreach (SheetProvider::findRestaurantSheets() as $sheet) {
                $dynamicRestaurantSubMenu
                    ->addItem(
                        (new MenuDTOItem($sheet->slug, $sheet->name, 'provider_portal_sheet_restaurant_index'))
                            ->setRouteParameters(['slug' => $sheet->slug])
                    )
                ;
            }
        }

        $dynamicActivitySubMenu = $menu->getItem(self::ACTIVITIES);
        if (null !== $dynamicActivitySubMenu) {
            foreach (SheetProvider::findActivitySheets() as $sheet) {
                $dynamicActivitySubMenu
                    ->addItem(
                        (new MenuDTOItem($sheet->slug, $sheet->name, 'provider_portal_sheet_activity_index'))
                            ->setRouteParameters(['slug' => $sheet->slug])
                    )
                ;
            }
        }

        $dynamicServiceSubMenu = $menu->getItem(self::SERVICES);
        if (null !== $dynamicServiceSubMenu) {
            foreach (SheetProvider::findServiceSheets() as $sheet) {
                $dynamicServiceSubMenu
                    ->addItem(
                        (new MenuDTOItem($sheet->slug, $sheet->name, 'provider_portal_sheet_service_index'))
                            ->setRouteParameters(['slug' => $sheet->slug])
                    )
                ;
            }
        }

        $dynamicMealTraySubMenu = $menu->getItem(self::MEAL_TRAYS);
        if (null !== $dynamicMealTraySubMenu) {
            foreach (SheetProvider::findMealTraySheets() as $sheet) {
                $dynamicMealTraySubMenu
                    ->addItem(
                        (new MenuDTOItem($sheet->slug, $sheet->name, 'provider_portal_sheet_meal_tray_index'))
                            ->setRouteParameters(['slug' => $sheet->slug])
                    )
                ;
            }
        }

        // Retrieve dynamic notifications
        $notifications = [
            self::INVOICES => 327,
            self::PLACES => 1,
            self::MEAL_TRAYS => 2,
        ];
        $notifications[self::SHEETS] = $notifications[self::PLACES] + $notifications[self::MEAL_TRAYS];

        foreach ($notifications as $code => $count) {
            $menuItem = $menu->getItem($code);
            if (null !== $menuItem) {
                $menuItem->setNotification($count);
            }
        }

        $this->items = $menu->getItems();
    }
}
