<?php

declare(strict_types=1);

namespace App\Account\Controller;

use App\Account\Entity\AccountInvitation;
use App\Account\Service\InvitationTokenSigner;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\Length;

final class InvitationController extends AbstractController
{
    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    #[Route('/invitation/{id}', name: 'app_account_invitation_accept', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], methods: ['GET', 'POST'])]
    public function accept(string $id, Request $request, EntityManagerInterface $entityManager, InvitationTokenSigner $signer, UserPasswordHasherInterface $hasher): Response
    {
        $invitation = $entityManager->find(AccountInvitation::class, $id);

        if (!$invitation instanceof AccountInvitation || !$invitation->isUsable() || !$signer->isValid($invitation, $request->query->getString('signature'))) {
            throw $this->createNotFoundException('Invitation expirée ou invalide.');
        }

        $form = $this->createFormBuilder()->setAction($request->getUri())
            ->add('password', RepeatedType::class, ['type' => PasswordType::class, 'first_options' => ['label' => 'Mot de passe'], 'second_options' => ['label' => 'Confirmer'], 'constraints' => [new Length(min: 12)]])
            ->add('submit', SubmitType::class, ['label' => 'Activer mon compte'])->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{password: string} $data */
            $data = $form->getData();
            $user = $invitation->user();
            $user->setPassword($hasher->hashPassword($user, $data['password']));
            $user->activate();
            $invitation->accept();
            $entityManager->flush();
            $this->addFlash('success', 'Compte activé. Vous pouvez vous connecter.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('account/invitation/accept.html.twig', ['form' => $form->createView(), 'email' => $invitation->user()->email()]);
    }
}
