<?php

namespace App\Pim\Twig\Components\Header\Menu;

use App\Pim\Model\ProviderPortal\DTO\UserDTO;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Menu mobile : mêmes entrées que la barre supérieure, plus la pastille de
 * l'utilisateur connecté (fournie par le gabarit, comme pour `Header:Profil`).
 */
#[AsTwigComponent]
final class BurgerMenu extends Menu
{
    public UserDTO $user;
}
