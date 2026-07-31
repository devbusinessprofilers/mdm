<?php

namespace App\Pim\Twig\Components\Footer\Menu;

use App\Pim\Model\ProviderPortal\DTO\Menu\MenuDTO;
use App\Pim\Model\ProviderPortal\DTO\Menu\MenuDTOItem;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('Footer:Menu', template: 'pim/components/Footer/Menu/Menu.html.twig')]
class Menu
{
    /**
     * @var array<MenuDTOItem>
     */
    public array $items = [];

    public function __construct()
    {
        $menu = (new MenuDTO())
            ->addItem(new MenuDTOItem('legal_notices', 'menu.footer.legal_notices', 'homepage'))
            ->addItem(new MenuDTOItem('cookies_policy', 'menu.footer.cookies_policy', 'homepage'))
            ->addItem(new MenuDTOItem('privacy_policy', 'menu.footer.privacy_policy', 'homepage'));

        $this->items = $menu->getItems() ?? [];
    }
}
