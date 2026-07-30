<?php

namespace App\Pim\Controller\ProviderPortal;

use App\Pim\Form\ProviderPortal\UserAccount\PersonalDataType;
use App\Pim\Form\ProviderPortal\UserAccount\SecurityType;
use App\Pim\Model\ProviderPortal\DTO\SecurityDTO;
use App\Pim\Model\ProviderPortal\DTO\UserDTO;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AccountController extends AbstractController
{
    #[Route(path: 'portal/account/personal-information', name: 'provider_portal_account_personal_information')]
    public function personalInformation(Request $request): Response
    {
        $form = $this->createForm(PersonalDataType::class, UserDTO::mock());
        $form->handleRequest($request);

        return $this->render('provider_portal/pages/online/account/personal-information.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route(path: 'portal/account/security', name: 'provider_portal_account_security')]
    public function security(Request $request): Response
    {
        $form = $this->createForm(SecurityType::class, SecurityDTO::mock());
        $form->handleRequest($request);

        return $this->render('provider_portal/pages/online/account/security.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route(path: 'portal/account/privacy', name: 'provider_portal_account_privacy')]
    public function privacy(): Response
    {
        return $this->render('provider_portal/pages/online/account/privacy.html.twig');
    }
}
