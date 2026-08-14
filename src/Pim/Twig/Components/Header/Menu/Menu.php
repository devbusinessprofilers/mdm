<?php

namespace App\Pim\Twig\Components\Header\Menu;

use App\Pim\Model\ProviderPortal\DTO\Menu\MenuDTOItem;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Barre de navigation supérieure — composant purement présentiel.
 *
 * Les entrées sont fournies par le gabarit via la propriété `items`
 * (cf. fonction Twig `entete_menu()` de l'EnteteExtension), qui les construit
 * à partir des routes réelles et des rôles de l'utilisateur.
 */
#[AsTwigComponent('Header:Menu', template: 'pim/components/Header/Menu/Menu.html.twig')]
class Menu
{
    /**
     * @var array<MenuDTOItem>
     */
    public array $items = [];
}
