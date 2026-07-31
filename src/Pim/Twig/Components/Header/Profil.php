<?php

namespace App\Pim\Twig\Components\Header;

use App\Pim\Model\ProviderPortal\DTO\UserDTO;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class Profil
{
    public object $user;
    public string $route;
    public bool $isMobile = false;
    public bool $isActive = false;

    public function __construct()
    {
        // Retrieve current User
        $this->user = UserDTO::mock();
    }

    public function mount(string $currentRoute, ?string $route = 'provider_portal_account_personal_information'): void
    {
        $this->route = $route;
        $this->isActive = $route === $currentRoute;
    }
}
