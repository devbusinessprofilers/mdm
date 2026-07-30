<?php

namespace App\Pim\Twig\Components\Menu\Sheet\MealTray;

use App\Pim\Enum\ProviderPortal\Twig\Component\Typography\TypographyTextColorEnum;
use App\Pim\Model\ProviderPortal\DTO\Menu\MenuDTO;
use App\Pim\Model\ProviderPortal\DTO\Menu\MenuDTOItem;
use App\Pim\Model\ProviderPortal\DTO\Menu\MenuDTOSection;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('Menu:Sheet:MealTray', template: 'pim/components/SideBar/Menu/Menu.html.twig')]
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
                (new MenuDTOSection('general', 'menu.sheet.meal_tray.general.title'))
                    ->addItem(
                        (new MenuDTOItem('main', 'menu.sheet.meal_tray.general.main', 'provider_portal_sheet_meal_tray_index'))
                            ->setIcon('info-circle')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                            ->setRouteParameters(['slug' => $this->slug])
                    )
                    ->addItem(
                        (new MenuDTOItem('description', 'menu.sheet.meal_tray.general.description', 'provider_portal_sheet_meal_tray_description'))
                            ->setIcon('note')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                            ->setRouteParameters(['slug' => $this->slug])
                    )
                    ->addItem(
                        (new MenuDTOItem('capacity', 'menu.sheet.meal_tray.general.product', 'provider_portal_sheet_meal_tray_product_list'))
                            ->setIcon('package')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                            ->setRouteParameters(['slug' => $this->slug])
                    )
                    ->addItem(
                        (new MenuDTOItem('csr', 'menu.sheet.meal_tray.general.csr', 'provider_portal_sheet_meal_tray_csr'))
                            ->setIcon('plant')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                            ->setRouteParameters(['slug' => $this->slug])
                    )
                    ->addItem(
                        (new MenuDTOItem('media', 'menu.sheet.meal_tray.general.media', 'provider_portal_sheet_meal_tray_media'))
                            ->setIcon('images')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                            ->setRouteParameters(['slug' => $this->slug])
                    )
                    ->addItem(
                        (new MenuDTOItem('visibility', 'menu.sheet.meal_tray.general.visibility', 'provider_portal_sheet_meal_tray_visibility'))
                            ->setIcon('rocket')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                            ->setRouteParameters(['slug' => $this->slug])
                    )
            )
            ->addSection(
                (new MenuDTOSection('setting', 'menu.sheet.meal_tray.setting.title'))
                    ->addItem(
                        (new MenuDTOItem('invoicing', 'menu.sheet.meal_tray.setting.invoicing', 'provider_portal_sheet_meal_tray_invoicing'))
                            ->setIcon('money')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                            ->setRouteParameters(['slug' => $this->slug])
                    )
                    ->addItem(
                        (new MenuDTOItem('collaborator', 'menu.sheet.meal_tray.setting.collaborator', 'provider_portal_sheet_membership_list'))
                            ->setIcon('users')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                            ->setRouteParameters(['slug' => $this->slug])
                    )
                    ->addItem(
                        (new MenuDTOItem('template', 'menu.sheet.meal_tray.setting.template', 'provider_portal_sheet_meal_tray_template'))
                            ->setIcon('layout')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                            ->setRouteParameters(['slug' => $this->slug])
                    )
            )
        ;

        $this->sections = $menu->getSections();
    }
}
