<?php

declare(strict_types=1);

namespace App\Account\MessageHandler;

use App\Account\Entity\PasswordResetRequest;
use App\Account\Message\InternalUserPasswordResetRequested;
use App\Account\Repository\PasswordResetRequestRepository;
use App\Account\Service\PasswordResetTokenSigner;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsMessageHandler]
final readonly class InternalUserPasswordResetRequestedHandler
{
    public function __construct(
        private PasswordResetRequestRepository $requests,
        private PasswordResetTokenSigner $signer,
        private UrlGeneratorInterface $urls,
        private MailerInterface $mailer,
        private string $sender,
    ) {
    }

    public function __invoke(InternalUserPasswordResetRequested $message): void
    {
        $request = $this->requests->find($message->requestId);
        if (!$request instanceof PasswordResetRequest || !$request->isUsable()) {
            return;
        }

        $url = $this->urls->generate('app_account_password_reset', [
            'id' => $request->id(),
            'signature' => $this->signer->sign($request),
        ], UrlGeneratorInterface::ABSOLUTE_URL);
        $this->mailer->send((new Email())
            ->from($this->sender)
            ->to($request->user()->email())
            ->subject('Réinitialisation de votre mot de passe PIM')
            ->text("Choisissez un nouveau mot de passe dans l’heure :\n\n".$url));
    }
}
