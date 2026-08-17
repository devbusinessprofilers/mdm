<?php

declare(strict_types=1);

namespace App\Account\Controller;

use App\Account\Entity\PasswordResetRequest;
use App\Account\Form\ForgotPasswordType;
use App\Account\Form\NewPasswordType;
use App\Account\Repository\PasswordResetRequestRepository;
use App\Account\Service\InternalUserManager;
use App\Account\Service\PasswordResetManager;
use App\Account\Service\PasswordResetRateLimiter;
use App\Account\Service\PasswordResetTokenSigner;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PasswordResetController extends AbstractController
{
    #[Route('/mot-de-passe-oublie', name: 'app_account_password_forgot', methods: ['GET', 'POST'])]
    public function forgot(
        Request $request,
        InternalUserManager $users,
        PasswordResetRateLimiter $rateLimiter,
    ): Response {
        $form = $this->createForm(ForgotPasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{email: string} $data */
            $data = $form->getData();
            if ($rateLimiter->accept($data['email'], $request->getClientIp() ?? 'unknown')) {
                $users->resendCredentialsByEmail($data['email']);
            }
            $this->addFlash('success', 'Si un compte correspond à cette adresse, un email vient d’être envoyé.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('account/password/forgot.html.twig', ['form' => $form->createView()]);
    }

    #[Route('/reinitialiser-mot-de-passe/{id}', name: 'app_account_password_reset', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], methods: ['GET', 'POST'])]
    public function reset(
        string $id,
        Request $request,
        PasswordResetRequestRepository $requests,
        PasswordResetTokenSigner $signer,
        PasswordResetManager $manager,
    ): Response {
        $passwordReset = $requests->find($id);
        if (!$passwordReset instanceof PasswordResetRequest
            || !$passwordReset->isUsable()
            || !$signer->isValid($passwordReset, $request->query->getString('signature'))
        ) {
            throw $this->createNotFoundException('Lien de réinitialisation expiré ou invalide.');
        }

        // Libellé de la maquette « Création du mot de passe » : le mot de passe
        // posé, l'écran renvoie droit vers la connexion.
        $form = $this->createForm(NewPasswordType::class, null, ['action' => $request->getUri(), 'button_label' => 'Se connecter']);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{password: string} $data */
            $data = $form->getData();
            $manager->reset($passwordReset, $data['password']);
            $this->addFlash('success', 'Votre mot de passe a été mis à jour.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('account/password/reset.html.twig', ['form' => $form->createView()]);
    }
}
