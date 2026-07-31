<?php

namespace App\Pim\Twig\Components\Menu\Chart\Dashboard;

use App\Pim\Enum\ProviderPortal\Twig\Component\Typography\TypographyTextColorEnum;
use App\Pim\Model\ProviderPortal\DTO\Menu\MenuDTO;
use App\Pim\Model\ProviderPortal\DTO\Menu\MenuDTOItem;
use App\Pim\Model\ProviderPortal\DTO\Menu\MenuDTOSection;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('Menu:Chart:Dashboard', template: 'pim/components/SideBar/Menu/Menu.html.twig')]
class Menu
{
    /**
     * @var array<MenuDTOSection>
     */
    public array $sections = [];

    public function mount(): void
    {
        $this->buildMenu();
    }

    public function buildMenu()
    {
        $menu = (new MenuDTO())
            ->addSection(
                (new MenuDTOSection('dashboard', 'menu.chart.dashboard.title'))
                    ->addItem(
                        (new MenuDTOItem('main', 'menu.chart.dashboard.title', 'provider_portal_chart_dashboard'))
                            ->setIcon('squares-four')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                    )
            )
        ;

        $this->sections = $menu->getSections();
    }
}
