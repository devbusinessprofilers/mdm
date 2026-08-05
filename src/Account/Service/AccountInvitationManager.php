<?php

declare(strict_types=1);

namespace App\Account\Service;

use App\Account\Entity\AccountInvitation;
use App\Account\Repository\AccountInvitationRepository;
use App\Account\Repository\PasswordResetRequestRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class AccountInvitationManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $hasher,
        private AccountInvitationRepository $invitations,
        private PasswordResetRequestRepository $passwordResets,
        private LoggerInterface $logger,
    ) {}

    public function accept(AccountInvitation $invitation, string $password): void
    {
        $user = $invitation->user();
        $this->entityManager->wrapInTransaction(function () use ($invitation, $user, $password): void {
            $this->entityManager->lock($user, LockMode::PESSIMISTIC_WRITE);
            $user->setPassword($this->hasher->hashPassword($user, $password));
            $user->activate();
            $invitation->accept();
            $this->invitations->invalidateUsableFor($user, $invitation->id());
            $this->passwordResets->invalidateUsableFor($user);
        });
        $this->logger->info('account.invitation.accepted', ['user_id' => $user->id()]);
    }
}
