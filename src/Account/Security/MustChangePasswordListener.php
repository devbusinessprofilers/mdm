<?php

declare(strict_types=1);

namespace App\Account\Security;

use App\Account\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Tant qu'un utilisateur doit changer son mot de passe (posé par un admin ou
 * un mot de passe par défaut), toute navigation est redirigée vers l'écran
 * « Changer mon mot de passe ». La déconnexion reste possible.
 */
final readonly class MustChangePasswordListener
{
    private const ROUTES_AUTORISEES = ['app_account_password_change', 'app_logout', 'app_login', 'app_login_legacy'];

    public function __construct(
        private Security $security,
        private UrlGeneratorInterface $urls,
    ) {
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 2)]
    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $route = $event->getRequest()->attributes->getString('_route');
        if ('' === $route || in_array($route, self::ROUTES_AUTORISEES, true)) {
            return;
        }
        $user = $this->security->getUser();
        if (!$user instanceof User || !$user->mustChangePassword()) {
            return;
        }
        $event->setResponse(new RedirectResponse($this->urls->generate('app_account_password_change')));
    }
}
