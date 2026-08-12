<?php

declare(strict_types=1);

namespace App\Account\Service;

use App\Account\Entity\AccountInvitation;
use App\Account\Entity\User;
use App\Account\Message\InternalUserInvited;
use App\Account\Repository\AccountInvitationRepository;
use App\Account\Repository\PasswordResetRequestRepository;
use App\Account\Repository\UserRepository;
use App\Shared\Outbox\OutboxPublisherInterface;
use App\Shared\Service\ParametreProviderInterface;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final readonly class InternalUserManager
{
    public function __construct(
        private UserRepository $users,
        private AccountInvitationRepository $invitations,
        private PasswordResetRequestRepository $passwordResets,
        private PasswordResetManager $passwordResetManager,
        private EntityManagerInterface $entityManager,
        private OutboxPublisherInterface $outbox,
        private ParametreProviderInterface $parametres,
        private LoggerInterface $logger,
    ) {}

    public function invite(string $email, string $role): void
    {
        if ($this->users->findOneByEmail($email) instanceof User) { throw new \DomainException('Un compte existe déjà pour cette adresse.'); }
        $user = new User($email, [$role]);
        $user->deactivate();
        $invitation = new AccountInvitation($user, $this->parametres->int('compte.invitation_validite_heures'));
        $this->entityManager->persist($user);
        $this->entityManager->persist($invitation);
        $this->outbox->enqueue(new InternalUserInvited($invitation->id()));
        $this->entityManager->flush();
        $this->logger->info('account.user.invited', ['user_id' => $user->id()]);
    }

    public function resendCredentialsByEmail(string $email): void
    {
        $user = $this->users->findOneByEmail($email);
        if ($user instanceof User) {
            $this->resendCredentials($user);
        }
    }

    public function resendCredentials(User $user): void
    {
        if ($user->isDeleted()) {
            throw new \DomainException('Ce compte a été supprimé.');
        }

        if (null !== $user->getPassword()) {
            $this->passwordResetManager->request($user);

            return;
        }

        $this->entityManager->wrapInTransaction(function () use ($user): void {
            $this->entityManager->lock($user, LockMode::PESSIMISTIC_WRITE);
            $this->invitations->invalidateUsableFor($user);
            $this->passwordResets->invalidateUsableFor($user);
            $invitation = new AccountInvitation($user, $this->parametres->int('compte.invitation_validite_heures'));
            $this->entityManager->persist($invitation);
            $this->outbox->enqueue(new InternalUserInvited($invitation->id()));
        });
        $this->logger->info('account.invitation.resent', ['user_id' => $user->id()]);
    }

    public function changeRole(User $user, string $role): void
    {
        if ($user->isDeleted()) {
            throw new \DomainException('Ce compte a été supprimé.');
        }
        $user->setRoles([$role]);
        $this->entityManager->flush();
    }

    public function toggle(User $user): void
    {
        if ($user->isDeleted()) {
            throw new \DomainException('Ce compte a été supprimé.');
        }
        $user->isActive() ? $user->deactivate() : $user->activate();
        $this->entityManager->flush();
    }

    /** L'utilisateur devra choisir un nouveau mot de passe à sa prochaine navigation. */
    public function forcePasswordChange(User $user): void
    {
        if ($user->isDeleted()) {
            throw new \DomainException('Ce compte a été supprimé.');
        }
        $user->requirePasswordChange();
        $this->entityManager->flush();
        $this->logger->info('account.password_change.forced', ['user_id' => $user->id()]);
    }

    public function delete(User $user, User $actor): void
    {
        if ($user->id() === $actor->id()) {
            throw new \DomainException('Vous ne pouvez pas supprimer votre propre compte.');
        }
        if (in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)) {
            throw new \DomainException('Les comptes Super Admin sont gérés en console.');
        }
        if ($user->isDeleted()) {
            throw new \DomainException('Ce compte est déjà supprimé.');
        }

        $targetId = $user->id();
        $this->entityManager->wrapInTransaction(function () use ($user): void {
            $this->entityManager->lock($user, LockMode::PESSIMISTIC_WRITE);
            $this->invitations->invalidateUsableFor($user);
            $this->passwordResets->invalidateUsableFor($user);
            $user->anonymize();
        });
        $this->logger->notice('account.user.deleted', [
            'user_id' => $targetId,
            'actor_id' => $actor->id(),
        ]);
    }
}
