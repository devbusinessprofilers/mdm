<?php

namespace App\Pim\Twig\Components\Header;

use App\Pim\Model\ProviderPortal\DTO\UserDTO;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Pastille de l'utilisateur connecté (en-tête et menu burger).
 *
 * L'utilisateur est toujours fourni par le gabarit (`entete_utilisateur()`) :
 * le composant ne connaît aucun profil par défaut.
 */
#[AsTwigComponent]
final class Profil
{
    public UserDTO $user;
    public string $route;
    public bool $isMobile = false;
    public bool $isActive = false;

    public function mount(string $currentRoute, string $route = 'app_mdm_espace_travail'): void
    {
        $this->route = $route;
        $this->isActive = $route === $currentRoute;
    }
}
