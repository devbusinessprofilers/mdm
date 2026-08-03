<?php

declare(strict_types=1);

namespace App\Pim\Twig;

use App\Pim\Maquette\EnteteMaquette;
use App\Pim\Model\ProviderPortal\DTO\Menu\MenuDTOItem;
use App\Pim\Model\ProviderPortal\DTO\UserDTO;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Expose le menu et l'utilisateur de la barre supérieure aux gabarits.
 *
 * La coquille en a besoin sur tous les écrans ; les faire passer par chaque
 * contrôleur reviendrait à répéter la même ligne partout. Les composants
 * `Header:Menu` et `Header:Profil` du portail restent intacts : ils reçoivent
 * simplement ces valeurs par leurs propriétés `items` et `user`.
 */
final class EnteteExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('entete_menu', $this->menu(...)),
            new TwigFunction('entete_utilisateur', $this->utilisateur(...)),
        ];
    }

    /**
     * @return list<MenuDTOItem>
     */
    public function menu(): array
    {
        return EnteteMaquette::menu();
    }

    public function utilisateur(): UserDTO
    {
        return EnteteMaquette::utilisateur();
    }
}
