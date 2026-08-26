<?php

declare(strict_types=1);

namespace App\Account\Controller;

use App\Account\Entity\User;
use App\Account\Form\AccountAdminFormFactory;
use App\Account\Repository\UserRepository;
use App\Account\Service\InternalUserManager;
use App\Shared\Service\ParametreProviderInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/utilisateurs', name: 'app_account_user_admin_')]
#[IsGranted('ROLE_SUPER_ADMIN')]
final class InternalUserAdminController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(UserRepository $users, AccountAdminFormFactory $forms): Response
    {
        $userEntities = $users->findForAdministration();
        $roleForms = [];
        $toggleForms = [];
        $resendForms = [];
        $deleteForms = [];
        $forcePasswordForms = [];

        foreach ($userEntities as $user) {
            if (in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)) {
                continue;
            }
            $roleForms[$user->id()] = $forms->internalRole($user)->createView();
            $toggleForms[$user->id()] = $forms->internalToggle($user, $user->isActive() ? 'Désactiver' : 'Activer')->createView();
            $resendForms[$user->id()] = $forms->internalResendCredentials($user)->createView();
            $deleteForms[$user->id()] = $forms->internalDelete($user)->createView();
            if (!$user->mustChangePassword()) {
                $forcePasswordForms[$user->id()] = $forms->internalForcePasswordChange($user)->createView();
            }
        }

        return $this->render('account/user_admin/index.html.twig', [
            'users' => $userEntities,
            'invite_form' => $forms->internalInvite()->createView(),
            'role_forms' => $roleForms,
            'toggle_forms' => $toggleForms,
            'resend_forms' => $resendForms,
            'delete_forms' => $deleteForms,
            'force_password_forms' => $forcePasswordForms,
        ]);
    }

    #[Route('/inviter', name: 'invite', methods: ['POST'])]
    public function invite(
        Request $request,
        AccountAdminFormFactory $forms,
        InternalUserManager $manager,
        ParametreProviderInterface $parametres,
    ): Response {
        $form = $forms->internalInvite();
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'Invitation invalide.');

            return $this->redirectToRoute('app_account_user_admin_index');
        }

        /** @var array{email: string, role: string} $data */
        $data = $form->getData();
        try {
            $manager->invite($data['email'], $data['role']);
            $this->addFlash('success', sprintf('Invitation envoyée pour %d heures.', $parametres->int('compte.invitation_validite_heures')));
        } catch (\DomainException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_account_user_admin_index');
    }

    #[Route('/{id}/role', name: 'role', methods: ['POST'])]
    public function role(string $id, Request $request, UserRepository $users, AccountAdminFormFactory $forms, InternalUserManager $manager): Response
    {
        $user = $users->find($id);
        if (!$user instanceof User || $user->isDeleted() || in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)) {
            throw $this->createNotFoundException();
        }
        $form = $forms->internalRole($user);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            throw $this->createAccessDeniedException();
        }
        /** @var array{role: string} $data */
        $data = $form->getData();
        $manager->changeRole($user, $data['role']);
        $this->addFlash('success', 'Rôle mis à jour.');

        return $this->redirectToRoute('app_account_user_admin_index');
    }

    #[Route('/{id}/etat', name: 'toggle', methods: ['POST'])]
    public function toggle(string $id, Request $request, UserRepository $users, AccountAdminFormFactory $forms, InternalUserManager $manager): Response
    {
        $user = $users->find($id);
        if (!$user instanceof User || $user->isDeleted() || in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)) {
            throw $this->createAccessDeniedException();
        }
        $form = $forms->internalToggle($user, 'Changer');
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            throw $this->createAccessDeniedException();
        }
        $manager->toggle($user);

        return $this->redirectToRoute('app_account_user_admin_index');
    }

    #[Route('/{id}/renvoyer-identifiants', name: 'resend_credentials', methods: ['POST'])]
    public function resendCredentials(string $id, Request $request, UserRepository $users, AccountAdminFormFactory $forms, InternalUserManager $manager): Response
    {
        $user = $users->find($id);
        if (!$user instanceof User || $user->isDeleted() || in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)) {
            throw $this->createNotFoundException();
        }
        $form = $forms->internalResendCredentials($user);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            throw $this->createAccessDeniedException();
        }

        $manager->resendCredentials($user);
        $this->addFlash('success', null === $user->getPassword() ? 'Invitation renvoyée.' : 'Lien de réinitialisation envoyé.');

        return $this->redirectToRoute('app_account_user_admin_index');
    }

    #[Route('/{id}/mot-de-passe-force', name: 'force_password', methods: ['POST'])]
    public function forcePassword(string $id, Request $request, UserRepository $users, AccountAdminFormFactory $forms, InternalUserManager $manager): Response
    {
        $user = $users->find($id);
        if (!$user instanceof User || $user->isDeleted() || in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)) {
            throw $this->createNotFoundException();
        }
        $form = $forms->internalForcePasswordChange($user);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            throw $this->createAccessDeniedException();
        }

        $manager->forcePasswordChange($user);
        $this->addFlash('success', 'Le collaborateur devra changer son mot de passe à sa prochaine connexion.');

        return $this->redirectToRoute('app_account_user_admin_index');
    }

    #[Route('/{id}/supprimer', name: 'delete', methods: ['POST'])]
    public function delete(string $id, Request $request, UserRepository $users, AccountAdminFormFactory $forms, InternalUserManager $manager): Response
    {
        $user = $users->find($id);
        $actor = $this->getUser();
        if (!$user instanceof User || !$actor instanceof User || $user->isDeleted() || in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)) {
            throw $this->createNotFoundException();
        }
        $form = $forms->internalDelete($user);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            throw $this->createAccessDeniedException();
        }

        $manager->delete($user, $actor);
        $this->addFlash('success', 'Collaborateur supprimé et anonymisé.');

        return $this->redirectToRoute('app_account_user_admin_index');
    }
}
