<?php

declare(strict_types=1);

namespace App\Account\Controller;

use App\Account\Entity\AccountInvitation;
use App\Account\Entity\User;
use App\Account\Message\InternalUserInvited;
use App\Account\Repository\UserRepository;
use App\Shared\Form\ActionType;
use App\Shared\Outbox\OutboxPublisherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/utilisateurs', name: 'app_account_user_admin_')]
#[IsGranted('ROLE_SUPER_ADMIN')]
final class InternalUserAdminController extends AbstractController
{
    private const ROLES = ['Éditeur' => 'ROLE_BP_EDITOR', 'Validateur' => 'ROLE_BP_VALIDATOR'];

    public function __construct(private readonly FormFactoryInterface $forms) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(UserRepository $users): Response
    {
        $userEntities = $users->findBy([], ['email' => 'ASC']);
        $roleForms = [];
        $toggleForms = [];

        foreach ($userEntities as $user) {
            if (in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)) { continue; }
            $roleForms[$user->id()] = $this->roleForm($user)->createView();
            $toggleForms[$user->id()] = $this->createForm(ActionType::class, null, [
                'action' => $this->generateUrl('app_account_user_admin_toggle', ['id' => $user->id()]),
                'button_label' => $user->isActive() ? 'Désactiver' : 'Activer',
                'csrf_token_id' => 'user-toggle-'.$user->id(),
            ])->createView();
        }

        $form = $this->createFormBuilder()->setAction($this->generateUrl('app_account_user_admin_invite'))
            ->add('email', EmailType::class)->add('role', ChoiceType::class, ['choices' => self::ROLES])
            ->add('submit', SubmitType::class, ['label' => 'Inviter'])->getForm();

        return $this->render('account/user_admin/index.html.twig', ['users' => $userEntities, 'invite_form' => $form->createView(), 'role_forms' => $roleForms, 'toggle_forms' => $toggleForms]);
    }

    #[Route('/inviter', name: 'invite', methods: ['POST'])]
    public function invite(Request $request, UserRepository $users, EntityManagerInterface $entityManager, OutboxPublisherInterface $outbox): Response
    {
        $form = $this->createFormBuilder()->add('email', EmailType::class)->add('role', ChoiceType::class, ['choices' => self::ROLES])->add('submit', SubmitType::class)->getForm();
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) { $this->addFlash('error', 'Invitation invalide.'); return $this->redirectToRoute('app_account_user_admin_index'); }
        /** @var array{email: string, role: string} $data */
        $data = $form->getData();

        if ($users->findOneByEmail($data['email']) instanceof User) { $this->addFlash('error', 'Un compte existe déjà pour cette adresse.'); return $this->redirectToRoute('app_account_user_admin_index'); }

        $user = new User($data['email'], [$data['role']]);
        $user->deactivate();
        $invitation = new AccountInvitation($user);
        $entityManager->persist($user);
        $entityManager->persist($invitation);
        $outbox->enqueue(new InternalUserInvited($invitation->id()));
        $entityManager->flush();
        $this->addFlash('success', 'Invitation envoyée pour 24 heures.');

        return $this->redirectToRoute('app_account_user_admin_index');
    }

    #[Route('/{id}/role', name: 'role', methods: ['POST'])]
    public function role(string $id, Request $request, UserRepository $users, EntityManagerInterface $entityManager): Response
    {
        $user = $users->find($id);
        if (!$user instanceof User || in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)) { throw $this->createNotFoundException(); }
        $form = $this->roleForm($user);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) { throw $this->createAccessDeniedException(); }
        /** @var array{role: string} $data */
        $data = $form->getData();
        $user->setRoles([$data['role']]);
        $entityManager->flush();
        $this->addFlash('success', 'Rôle mis à jour.');
        return $this->redirectToRoute('app_account_user_admin_index');
    }

    #[Route('/{id}/etat', name: 'toggle', methods: ['POST'])]
    public function toggle(string $id, Request $request, UserRepository $users, EntityManagerInterface $entityManager): Response
    {
        $user = $users->find($id);
        if (!$user instanceof User || in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)) { throw $this->createAccessDeniedException(); }
        $form = $this->createForm(ActionType::class, null, ['action' => $this->generateUrl('app_account_user_admin_toggle', ['id' => $id]), 'button_label' => 'Changer', 'csrf_token_id' => 'user-toggle-'.$id]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) { throw $this->createAccessDeniedException(); }
        $user->isActive() ? $user->deactivate() : $user->activate();
        $entityManager->flush();
        return $this->redirectToRoute('app_account_user_admin_index');
    }

    /** @return FormInterface<mixed> */
    private function roleForm(User $user): FormInterface
    {
        $current = in_array('ROLE_BP_VALIDATOR', $user->getRoles(), true) ? 'ROLE_BP_VALIDATOR' : 'ROLE_BP_EDITOR';
        return $this->forms->createNamedBuilder('role_'.$user->id(), data: ['role' => $current])
            ->setAction($this->generateUrl('app_account_user_admin_role', ['id' => $user->id()]))
            ->add('role', ChoiceType::class, ['choices' => self::ROLES])
            ->add('submit', SubmitType::class, ['label' => 'Changer'])
            ->getForm();
    }
}
