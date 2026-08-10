<?php

declare(strict_types=1);

namespace App\Account\Controller;

use App\Account\Entity\User;
use App\Account\Form\NewPasswordType;
use App\Account\Service\PasswordResetManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Écran connecté « Changer mon mot de passe » : point de passage obligé tant
 * que le compte porte un mot de passe posé pour l'utilisateur (défaut, admin).
 */
final class PasswordChangeController extends AbstractController
{
    #[Route('/changer-mon-mot-de-passe', name: 'app_account_password_change', methods: ['GET', 'POST'])]
    public function change(Request $request, PasswordResetManager $manager): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(NewPasswordType::class, null, [
            'action' => $this->generateUrl('app_account_password_change'),
            'button_label' => 'Changer mon mot de passe',
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{password: string} $data */
            $data = $form->getData();
            $manager->changeOwnPassword($user, $data['password']);
            $this->addFlash('success', 'Votre mot de passe a été mis à jour.');

            return $this->redirectToRoute('app_pim_home');
        }

        return $this->render('account/password/change.html.twig', [
            'form' => $form->createView(),
            'obligatoire' => $user->mustChangePassword(),
        ]);
    }
}
