<?php

namespace App\Pim\Twig\Components\Menu\Sheet\Restaurant;

use App\Pim\Enum\ProviderPortal\Twig\Component\Typography\TypographyTextColorEnum;
use App\Pim\Model\ProviderPortal\DTO\Menu\MenuDTO;
use App\Pim\Model\ProviderPortal\DTO\Menu\MenuDTOItem;
use App\Pim\Model\ProviderPortal\DTO\Menu\MenuDTOSection;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('Menu:Sheet:Restaurant', template: 'pim/components/SideBar/Menu/Menu.html.twig')]
class Menu
{
    /**
     * @var array<MenuDTOSection>
     */
    public array $sections = [];

    public ?string $slug = null;

    public function mount(string $slug): void
    {
        $this->slug = $slug;
        $this->buildMenu();
    }

    public function buildMenu()
    {
        $menu = (new MenuDTO())
            ->addSection(
                (new MenuDTOSection('general', 'menu.sheet.restaurant.general.title'))
                    ->addItem(
                        (new MenuDTOItem('main', 'menu.sheet.restaurant.general.main', 'provider_portal_sheet_restaurant_index'))
                            ->setIcon('info-circle')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                            ->setRouteParameters(['slug' => $this->slug])
                    )
                    ->addItem(
                        (new MenuDTOItem('localisation', 'menu.sheet.restaurant.general.localisation', 'provider_portal_sheet_restaurant_localisation'))
                            ->setIcon('pin')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                            ->setRouteParameters(['slug' => $this->slug])
                    )
                    ->addItem(
                        (new MenuDTOItem('capacity', 'menu.sheet.restaurant.general.capacity', 'provider_portal_sheet_restaurant_capacity'))
                            ->setIcon('utensils')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                            ->setRouteParameters(['slug' => $this->slug])
                    )
                    ->addItem(
                        (new MenuDTOItem('facility', 'menu.sheet.restaurant.general.facility', 'provider_portal_sheet_restaurant_facility'))
                            ->setIcon('lectern')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                            ->setRouteParameters(['slug' => $this->slug])
                    )
                    ->addItem(
                        (new MenuDTOItem('csr', 'menu.sheet.restaurant.general.csr', 'provider_portal_sheet_restaurant_csr'))
                            ->setIcon('plant')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                            ->setRouteParameters(['slug' => $this->slug])
                    )
                    ->addItem(
                        (new MenuDTOItem('price', 'menu.sheet.restaurant.general.price', 'provider_portal_sheet_restaurant_price'))
                            ->setIcon('currency-euro')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                            ->setRouteParameters(['slug' => $this->slug])
                    )
                    ->addItem(
                        (new MenuDTOItem('media', 'menu.sheet.restaurant.general.media', 'provider_portal_sheet_restaurant_media'))
                            ->setIcon('images')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                            ->setRouteParameters(['slug' => $this->slug])
                    )
                    ->addItem(
                        (new MenuDTOItem('visibility', 'menu.sheet.restaurant.general.visibility', 'provider_portal_sheet_restaurant_visibility'))
                            ->setIcon('rocket')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                            ->setRouteParameters(['slug' => $this->slug])
                    )
            )
            ->addSection(
                (new MenuDTOSection('setting', 'menu.sheet.restaurant.setting.title'))
                    ->addItem(
                        (new MenuDTOItem('invoicing', 'menu.sheet.restaurant.setting.invoicing', 'provider_portal_sheet_restaurant_invoicing'))
                            ->setIcon('money')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                            ->setRouteParameters(['slug' => $this->slug])
                    )
                    ->addItem(
                        (new MenuDTOItem('collaborator', 'menu.sheet.restaurant.setting.collaborator', 'provider_portal_sheet_membership_list'))
                            ->setIcon('users')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                            ->setRouteParameters(['slug' => $this->slug])
                    )
                    ->addItem(
                        (new MenuDTOItem('template', 'menu.sheet.restaurant.setting.template', 'provider_portal_sheet_restaurant_template'))
                            ->setIcon('layout')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                            ->setRouteParameters(['slug' => $this->slug])
                    )
            )
        ;

        $this->sections = $menu->getSections();
    }
}
