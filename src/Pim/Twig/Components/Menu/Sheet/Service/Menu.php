<?php

namespace App\Pim\Twig\Components\Menu\Sheet\Service;

use App\Pim\Enum\ProviderPortal\Twig\Component\Typography\TypographyTextColorEnum;
use App\Pim\Model\ProviderPortal\DTO\Menu\MenuDTO;
use App\Pim\Model\ProviderPortal\DTO\Menu\MenuDTOItem;
use App\Pim\Model\ProviderPortal\DTO\Menu\MenuDTOSection;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('Menu:Sheet:Service', template: 'pim/components/SideBar/Menu/Menu.html.twig')]
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
                (new MenuDTOSection('general', 'menu.sheet.service.general.title'))
                    ->addItem(
                        (new MenuDTOItem('main', 'menu.sheet.service.general.main', 'provider_portal_sheet_service_index'))
                            ->setIcon('info-circle')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                            ->setRouteParameters(['slug' => $this->slug])
                    )
                    ->addItem(
                        (new MenuDTOItem('localisation', 'menu.sheet.service.general.localisation', 'provider_portal_sheet_service_localisation'))
                            ->setIcon('pin')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                            ->setRouteParameters(['slug' => $this->slug])
                    )
                    ->addItem(
                        (new MenuDTOItem('detail', 'menu.sheet.service.general.detail', 'provider_portal_sheet_service_detail'))
                            ->setIcon('lectern')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                            ->setRouteParameters(['slug' => $this->slug])
                    )
                    ->addItem(
                        (new MenuDTOItem('price', 'menu.sheet.service.general.price', 'provider_portal_sheet_service_price'))
                            ->setIcon('currency-euro')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                            ->setRouteParameters(['slug' => $this->slug])
                    )
                    ->addItem(
                        (new MenuDTOItem('media', 'menu.sheet.service.general.media', 'provider_portal_sheet_service_media'))
                            ->setIcon('images')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                            ->setRouteParameters(['slug' => $this->slug])
                    )
                    ->addItem(
                        (new MenuDTOItem('visibility', 'menu.sheet.service.general.visibility', 'provider_portal_sheet_service_visibility'))
                            ->setIcon('rocket')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                            ->setRouteParameters(['slug' => $this->slug])
                    )
            )
            ->addSection(
                (new MenuDTOSection('setting', 'menu.sheet.service.setting.title'))
                    ->addItem(
                        (new MenuDTOItem('invoicing', 'menu.sheet.service.setting.invoicing', 'provider_portal_sheet_service_invoicing'))
                            ->setIcon('money')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                            ->setRouteParameters(['slug' => $this->slug])
                    )
                    ->addItem(
                        (new MenuDTOItem('collaborator', 'menu.sheet.service.setting.collaborator', 'provider_portal_sheet_membership_list'))
                            ->setIcon('users')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                            ->setRouteParameters(['slug' => $this->slug])
                    )
                    ->addItem(
                        (new MenuDTOItem('template', 'menu.sheet.service.setting.template', 'provider_portal_sheet_service_template'))
                            ->setIcon('layout')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                            ->setRouteParameters(['slug' => $this->slug])
                    )
            )
        ;

        $this->sections = $menu->getSections();
    }
}
