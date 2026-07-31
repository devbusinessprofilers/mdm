<?php

namespace App\Pim\Twig\Components\Menu\Review;

use App\Pim\Enum\ProviderPortal\Twig\Component\Typography\TypographyTextColorEnum;
use App\Pim\Model\ProviderPortal\DTO\Menu\MenuDTO;
use App\Pim\Model\ProviderPortal\DTO\Menu\MenuDTOItem;
use App\Pim\Model\ProviderPortal\DTO\Menu\MenuDTOSection;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('Menu:Review', template: 'pim/components/SideBar/Menu/Menu.html.twig')]
class Menu
{
    /**
     * @var array<MenuDTOSection>
     */
    public array $sections = [];

    public function __construct()
    {
        $menu = (new MenuDTO())
            ->addSection(
                (new MenuDTOSection('review', 'menu.review.title'))
                    ->addItem(
                        (new MenuDTOItem('received', 'menu.review.received', 'provider_portal_review_received'))
                            ->setIcon('star')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                    )
                    ->addItem(
                        (new MenuDTOItem('reminder', 'menu.review.reminder', 'provider_portal_review_reminder'))
                            ->setIcon('arrow-clockwise')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                    )
            );

        $this->sections = $menu->getSections();
    }
}
