<?php

namespace App\Pim\Twig\Components\Menu\Sheet\Place;

use App\Pim\Enum\ProviderPortal\Twig\Component\Typography\TypographyTextColorEnum;
use App\Pim\Model\ProviderPortal\DTO\Menu\MenuDTO;
use App\Pim\Model\ProviderPortal\DTO\Menu\MenuDTOItem;
use App\Pim\Model\ProviderPortal\DTO\Menu\MenuDTOSection;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('Menu:Sheet:Place', template: 'pim/components/SideBar/Menu/Menu.html.twig')]
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
                (new MenuDTOSection('general', 'menu.sheet.place.general.title'))
                    ->addItem(
                        (new MenuDTOItem('main', 'menu.sheet.place.general.main', 'provider_portal_sheet_place_index'))
                            ->setIcon('info-circle')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                            ->setRouteParameters(['slug' => $this->slug])
                    )
                    ->addItem(
                        (new MenuDTOItem('localisation', 'menu.sheet.place.general.localisation', 'provider_portal_sheet_place_localisation'))
                            ->setIcon('pin')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                            ->setRouteParameters(['slug' => $this->slug])
                    )
                    ->addItem(
                        (new MenuDTOItem('thematic', 'menu.sheet.place.general.thematic', 'provider_portal_sheet_place_thematic'))
                            ->setIcon('confetti')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                            ->setRouteParameters(['slug' => $this->slug])
                    )
                    ->addItem(
                        (new MenuDTOItem('description', 'menu.sheet.place.general.description', 'provider_portal_sheet_place_description'))
                            ->setIcon('note')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                            ->setRouteParameters(['slug' => $this->slug])
                    )
                    ->addItem(
                        (new MenuDTOItem('accommodation', 'menu.sheet.place.general.accommodation', 'provider_portal_sheet_place_accommodation'))
                            ->setIcon('bed')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                            ->setRouteParameters(['slug' => $this->slug])
                    )
                    ->addItem(
                        (new MenuDTOItem('meeting', 'menu.sheet.place.general.meeting', 'provider_portal_sheet_place_meeting'))
                            ->setIcon('user-rectangle')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                            ->setRouteParameters(['slug' => $this->slug])
                    )
                    ->addItem(
                        (new MenuDTOItem('catering', 'menu.sheet.place.general.catering', 'provider_portal_sheet_place_catering'))
                            ->setIcon('utensils')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                            ->setRouteParameters(['slug' => $this->slug])
                    )
                    ->addItem(
                        (new MenuDTOItem('leisure', 'menu.sheet.place.general.leisure', 'provider_portal_sheet_place_leisure'))
                            ->setIcon('biking')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                            ->setRouteParameters(['slug' => $this->slug])
                    )
                    ->addItem(
                        (new MenuDTOItem('services', 'menu.sheet.place.general.services', 'provider_portal_sheet_place_services'))
                            ->setIcon('lectern')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                            ->setRouteParameters(['slug' => $this->slug])
                    )
                    ->addItem(
                        (new MenuDTOItem('csr', 'menu.sheet.place.general.csr', 'provider_portal_sheet_place_csr'))
                            ->setIcon('plant')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                            ->setRouteParameters(['slug' => $this->slug])
                    )
                    ->addItem(
                        (new MenuDTOItem('price', 'menu.sheet.place.general.prices', 'provider_portal_sheet_place_prices'))
                            ->setIcon('currency-euro')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                            ->setRouteParameters(['slug' => $this->slug])
                    )
                    ->addItem(
                        (new MenuDTOItem('media', 'menu.sheet.place.general.media', 'provider_portal_sheet_place_media'))
                            ->setIcon('images')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                            ->setRouteParameters(['slug' => $this->slug])
                    )
                    ->addItem(
                        (new MenuDTOItem('visibility', 'menu.sheet.place.general.visibility', 'provider_portal_sheet_place_visibility'))
                            ->setIcon('rocket')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                            ->setRouteParameters(['slug' => $this->slug])
                    )
            )
            ->addSection(
                (new MenuDTOSection('setting', 'menu.sheet.place.setting.title'))
                    ->addItem(
                        (new MenuDTOItem('invoicing', 'menu.sheet.place.setting.invoicing', 'provider_portal_sheet_place_invoicing'))
                            ->setIcon('money')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                            ->setRouteParameters(['slug' => $this->slug])
                    )
                    ->addItem(
                        (new MenuDTOItem('collaborator', 'menu.sheet.place.setting.collaborator', 'provider_portal_sheet_membership_list'))
                            ->setIcon('users')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                            ->setRouteParameters(['slug' => $this->slug])
                    )
                    ->addItem(
                        (new MenuDTOItem('template', 'menu.sheet.place.setting.template', 'provider_portal_sheet_place_template'))
                            ->setIcon('layout')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                            ->setRouteParameters(['slug' => $this->slug])
                    )
            )
        ;

        $this->sections = $menu->getSections();
    }
}
