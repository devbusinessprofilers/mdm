<?php

declare(strict_types=1);

namespace App\Account\Controller;

use App\Account\Form\LoginType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if (null !== $this->getUser()) {
            return new RedirectResponse($this->generateUrl('app_pim_home'));
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'authentication_error' => $authenticationUtils->getLastAuthenticationError(),
            'login_form' => $this->createForm(LoginType::class, ['email' => $authenticationUtils->getLastUsername()], [
                'action' => $this->generateUrl('app_login'),
            ])->createView(),
        ]);
    }

    #[Route('/logout', name: 'app_logout', methods: ['POST'])]
    public function logout(): never
    {
        throw new \LogicException('This method is intercepted by the security firewall.');
    }
}
