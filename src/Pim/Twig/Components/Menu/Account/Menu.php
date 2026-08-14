<?php

namespace App\Pim\Twig\Components\Menu\Account;

use App\Pim\Enum\ProviderPortal\Twig\Component\Typography\TypographyTextColorEnum;
use App\Pim\Model\ProviderPortal\DTO\Menu\MenuDTO;
use App\Pim\Model\ProviderPortal\DTO\Menu\MenuDTOItem;
use App\Pim\Model\ProviderPortal\DTO\Menu\MenuDTOSection;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('Menu:Account', template: 'pim/components/SideBar/Menu/Menu.html.twig')]
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
                (new MenuDTOSection('account', 'menu.account.title'))
                    ->addItem(
                        (new MenuDTOItem('personal_information', 'menu.account.personal_information', 'provider_portal_account_personal_information'))
                            ->setIcon('identification-card')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                    )
                    ->addItem(
                        (new MenuDTOItem('security', 'menu.account.security', 'provider_portal_account_security'))
                            ->setIcon('lock-outline')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                    )
                    ->addItem(
                        (new MenuDTOItem('confidentiality', 'menu.account.confidentiality', 'provider_portal_account_privacy'))
                            ->setIcon('shield-check')
                            ->setIconColor(TypographyTextColorEnum::PRIMARY)
                    )
            );

        $this->sections = $menu->getSections();
    }
}
