<?php

namespace App\Pim\Controller\ProviderPortal;

use App\Pim\Form\ProviderPortal\Auth\CreatePasswordType;
use App\Pim\Form\ProviderPortal\Auth\EnterPasswordType;
use App\Pim\Form\ProviderPortal\Auth\ForgotPasswordType;
use App\Pim\Form\ProviderPortal\Auth\LoginEmailType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AuthController extends AbstractController
{
    #[Route(path: 'portal/login', name: 'provider_portal_login')]
    public function login(Request $request): Response
    {
        $form = $this->createForm(LoginEmailType::class);
        $form->handleRequest($request);

        return $this->render('provider_portal/pages/auth/login.html.twig', [
            'backgroundUrl' => 'provider_portal/img/mock/bg-auth-1.png',
            'form' => $form,
        ]);
    }

    #[Route(path: 'portal/enter-password', name: 'provider_portal_enter_password')]
    public function enterPassword(Request $request): Response
    {
        $form = $this->createForm(EnterPasswordType::class);
        $form->handleRequest($request);

        return $this->render('provider_portal/pages/auth/enter-password.html.twig', [
            'backgroundUrl' => 'provider_portal/img/mock/bg-auth-3.png',
            'currentUserEmail' => 'user@yopmail.com',
            'form' => $form,
        ]);
    }

    #[Route(path: 'portal/forgot-password', name: 'provider_portal_forgot_password')]
    public function forgotPassword(Request $request): Response
    {
        $form = $this->createForm(ForgotPasswordType::class);
        $form->handleRequest($request);

        return $this->render('provider_portal/pages/auth/forgot-password.html.twig', [
            'backgroundUrl' => 'provider_portal/img/mock/bg-auth-4.png',
            'form' => $form,
        ]);
    }

    #[Route(path: 'portal/create-password', name: 'provider_portal_create_password')]
    public function createPassword(Request $request): Response
    {
        $form = $this->createForm(CreatePasswordType::class);
        $form->handleRequest($request);

        return $this->render('provider_portal/pages/auth/create-password.html.twig', [
            'backgroundUrl' => 'provider_portal/img/mock/bg-auth-2.png',
            'form' => $form,
        ]);
    }
}
