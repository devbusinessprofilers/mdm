<?php

declare(strict_types=1);

namespace App\Account\Controller;

use App\Account\Entity\AccountInvitation;
use App\Account\Form\NewPasswordType;
use App\Account\Repository\AccountInvitationRepository;
use App\Account\Service\AccountInvitationManager;
use App\Account\Service\InvitationTokenSigner;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class InvitationController extends AbstractController
{
    #[Route('/invitation/{id}', name: 'app_account_invitation_accept', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], methods: ['GET', 'POST'])]
    public function accept(
        string $id,
        Request $request,
        AccountInvitationRepository $invitations,
        InvitationTokenSigner $signer,
        AccountInvitationManager $manager,
    ): Response {
        $invitation = $invitations->find($id);

        if (!$invitation instanceof AccountInvitation || !$invitation->isUsable() || !$signer->isValid($invitation, $request->query->getString('signature'))) {
            throw $this->createNotFoundException('Invitation expirée ou invalide.');
        }

        $form = $this->createForm(NewPasswordType::class, null, [
            'action' => $request->getUri(),
            'button_label' => 'Activer mon compte',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{password: string} $data */
            $data = $form->getData();
            $manager->accept($invitation, $data['password']);
            $this->addFlash('success', 'Compte activé. Vous pouvez vous connecter.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('account/invitation/accept.html.twig', [
            'form' => $form->createView(),
            'email' => $invitation->user()->email(),
        ]);
    }
}
