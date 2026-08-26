<?php

declare(strict_types=1);

namespace App\Shared\Service;

use App\Account\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Transmet un signalement de bug à l'équipe technique, enrichi du contexte
 * de l'utilisateur connecté (identité, rôles, page d'origine, navigateur).
 */
final readonly class SignalementBugMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private Security $security,
        private string $sender,
        private string $destinataire,
    ) {}

    public function envoyer(string $titre, string $description, ?string $page, ?string $navigateur): void
    {
        $user = $this->security->getUser();
        $identite = 'inconnu';
        $roles = '';
        if ($user instanceof User) {
            $identite = $user->email();
            $roles = implode(', ', $user->getRoles());
        }

        $email = (new Email())
            ->from($this->sender)
            ->to($this->destinataire)
            ->subject(sprintf('[PIM] Bug : %s', $titre))
            ->text(sprintf(
                "Signalement envoyé depuis le PIM.\n\n"
                ."Collaborateur : %s\nRôles : %s\nPage d'origine : %s\nNavigateur : %s\n\n%s",
                $identite,
                '' === $roles ? '—' : $roles,
                $page ?? '—',
                $navigateur ?? '—',
                $description,
            ));

        if ($user instanceof User) {
            $email->replyTo($user->email());
        }

        $this->mailer->send($email);
    }
}
