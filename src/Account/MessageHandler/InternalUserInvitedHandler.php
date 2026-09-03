<?php

declare(strict_types=1);

namespace App\Account\MessageHandler;

use App\Account\Entity\AccountInvitation;
use App\Account\Message\InternalUserInvited;
use App\Account\Repository\AccountInvitationRepository;
use App\Account\Service\InvitationTokenSigner;
use App\Shared\Service\ParametreProviderInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsMessageHandler]
final readonly class InternalUserInvitedHandler
{
    public function __construct(
        private AccountInvitationRepository $invitations,
        private InvitationTokenSigner $signer,
        private UrlGeneratorInterface $urls,
        private MailerInterface $mailer,
        private ParametreProviderInterface $parametres,
        private string $sender,
    ) {
    }

    public function __invoke(InternalUserInvited $message): void
    {
        $invitation = $this->invitations->find($message->invitationId);
        if (!$invitation instanceof AccountInvitation || !$invitation->isUsable()) {
            return;
        }
        $url = $this->urls->generate('app_account_invitation_accept', [
            'id' => $invitation->id(),
            'signature' => $this->signer->sign($invitation),
        ], UrlGeneratorInterface::ABSOLUTE_URL);
        $this->mailer->send((new Email())
            ->from($this->sender)
            ->to($invitation->user()->email())
            ->subject('Votre accès au PIM Business Profilers')
            ->text(sprintf(
                "Créez votre mot de passe dans les %d heures :\n\n%s",
                $this->parametres->int('compte.invitation_validite_heures'),
                $url,
            )));
    }
}
