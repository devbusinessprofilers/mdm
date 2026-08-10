<?php

declare(strict_types=1);

namespace App\Account\MessageHandler;

use App\Account\Message\CollaborateurAccessRequested;
use App\Pim\Entity\FicheCollaborateur;
use App\Pim\Repository\FicheCollaborateurRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;

#[AsMessageHandler]
final readonly class CollaborateurAccessRequestedHandler
{
    public function __construct(
        private FicheCollaborateurRepository $collaborateurs,
        private MailerInterface $mailer,
        private string $sender,
    ) {}

    public function __invoke(CollaborateurAccessRequested $message): void
    {
        $collaborateur = $this->collaborateurs->find($message->collaborateurId);
        if (!$collaborateur instanceof FicheCollaborateur || !$collaborateur->isActive()) { return; }
        $body = trim($message->emailBody);
        if ('' === $body) {
            $body = 'Bonjour,'."\n\n".'Vos accès à l’extranet Business Profilers vont vous être communiqués prochainement.';
        }
        $this->mailer->send((new Email())
            ->from($this->sender)
            ->to($collaborateur->email())
            ->subject('Vos accès extranet Business Profilers')
            ->text($body));
    }
}
