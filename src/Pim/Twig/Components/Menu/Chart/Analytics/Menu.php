<?php

namespace App\Pim\Twig\Components\Menu\Chart\Analytics;

use App\Pim\Enum\ProviderPortal\Twig\Component\Typography\TypographyTextColorEnum;
use App\Pim\Model\ProviderPortal\DTO\Menu\MenuDTO;
use App\Pim\Model\ProviderPortal\DTO\Menu\MenuDTOItem;
use App\Pim\Model\ProviderPortal\DTO\Menu\MenuDTOSection;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('Menu:Chart:Analytics', template: 'pim/components/SideBar/Menu/Menu.html.twig')]
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
                (new MenuDTOSection('general', 'menu.chart.analytics.title'))
                    ->addItem(
                        (new MenuDTOItem('main', 'menu.chart.analytics.performance', 'provider_portal_chart_analytics'))
                            ->setIcon('spin')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                    )
                    ->addItem(
                        (new MenuDTOItem('localisation', 'menu.chart.analytics.competition', 'provider_portal_chart_competition'))
                            ->setIcon('confettis')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                    )
            )
        ;

        $this->sections = $menu->getSections();
    }
}
