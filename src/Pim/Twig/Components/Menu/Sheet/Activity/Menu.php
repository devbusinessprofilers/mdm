<?php

namespace App\Pim\Twig\Components\Menu\Sheet\Activity;

use App\Pim\Enum\ProviderPortal\Twig\Component\Typography\TypographyTextColorEnum;
use App\Pim\Model\ProviderPortal\DTO\Menu\MenuDTO;
use App\Pim\Model\ProviderPortal\DTO\Menu\MenuDTOItem;
use App\Pim\Model\ProviderPortal\DTO\Menu\MenuDTOSection;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('Menu:Sheet:Activity', template: 'pim/components/SideBar/Menu/Menu.html.twig')]
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
                (new MenuDTOSection('general', 'menu.sheet.activity.general.title'))
                    ->addItem(
                        (new MenuDTOItem('main', 'menu.sheet.activity.general.main', 'provider_portal_sheet_activity_index'))
                            ->setIcon('info-circle')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                            ->setRouteParameters(['slug' => $this->slug])
                    )
                    ->addItem(
                        (new MenuDTOItem('localisation', 'menu.sheet.activity.general.localisation', 'provider_portal_sheet_activity_localisation'))
                            ->setIcon('pin')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                            ->setRouteParameters(['slug' => $this->slug])
                    )
                    ->addItem(
                        (new MenuDTOItem('description', 'menu.sheet.activity.general.description', 'provider_portal_sheet_activity_description'))
                            ->setIcon('note')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                            ->setRouteParameters(['slug' => $this->slug])
                    )
                    ->addItem(
                        (new MenuDTOItem('capacity', 'menu.sheet.activity.general.capacity', 'provider_portal_sheet_activity_capacity'))
                            ->setIcon('users')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                            ->setRouteParameters(['slug' => $this->slug])
                    )
                    ->addItem(
                        (new MenuDTOItem('csr', 'menu.sheet.activity.general.csr', 'provider_portal_sheet_activity_csr'))
                            ->setIcon('plant')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                            ->setRouteParameters(['slug' => $this->slug])
                    )
                    ->addItem(
                        (new MenuDTOItem('price', 'menu.sheet.activity.general.price', 'provider_portal_sheet_activity_price'))
                            ->setIcon('currency-euro')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                            ->setRouteParameters(['slug' => $this->slug])
                    )
                    ->addItem(
                        (new MenuDTOItem('media', 'menu.sheet.activity.general.media', 'provider_portal_sheet_activity_media'))
                            ->setIcon('images')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                            ->setRouteParameters(['slug' => $this->slug])
                    )
                    ->addItem(
                        (new MenuDTOItem('visibility', 'menu.sheet.activity.general.visibility', 'provider_portal_sheet_activity_visibility'))
                            ->setIcon('rocket')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                            ->setRouteParameters(['slug' => $this->slug])
                    )
            )
            ->addSection(
                (new MenuDTOSection('setting', 'menu.sheet.activity.setting.title'))
                    ->addItem(
                        (new MenuDTOItem('invoicing', 'menu.sheet.activity.setting.invoicing', 'provider_portal_sheet_activity_invoicing'))
                            ->setIcon('money')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                            ->setRouteParameters(['slug' => $this->slug])
                    )
                    ->addItem(
                        (new MenuDTOItem('collaborator', 'menu.sheet.activity.setting.collaborator', 'provider_portal_sheet_membership_list'))
                            ->setIcon('users')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                            ->setRouteParameters(['slug' => $this->slug])
                    )
                    ->addItem(
                        (new MenuDTOItem('template', 'menu.sheet.activity.setting.template', 'provider_portal_sheet_activity_template'))
                            ->setIcon('layout')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                            ->setRouteParameters(['slug' => $this->slug])
                    )
            )
        ;

        $this->sections = $menu->getSections();
    }
}
