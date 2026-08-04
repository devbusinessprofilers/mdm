<?php

declare(strict_types=1);

namespace App\Account\Service;

use App\Account\Entity\AccountInvitation;
use App\Account\Entity\User;
use App\Account\Message\InternalUserInvited;
use App\Account\Repository\UserRepository;
use App\Shared\Outbox\OutboxPublisherInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class InternalUserManager
{
    public function __construct(
        private UserRepository $users,
        private EntityManagerInterface $entityManager,
        private OutboxPublisherInterface $outbox,
    ) {}

    public function invite(string $email, string $role): void
    {
        if ($this->users->findOneByEmail($email) instanceof User) { throw new \DomainException('Un compte existe déjà pour cette adresse.'); }
        $user = new User($email, [$role]);
        $user->deactivate();
        $invitation = new AccountInvitation($user);
        $this->entityManager->persist($user);
        $this->entityManager->persist($invitation);
        $this->outbox->enqueue(new InternalUserInvited($invitation->id()));
        $this->entityManager->flush();
    }

    public function changeRole(User $user, string $role): void
    {
        $user->setRoles([$role]);
        $this->entityManager->flush();
    }

    public function toggle(User $user): void
    {
        $user->isActive() ? $user->deactivate() : $user->activate();
        $this->entityManager->flush();
    }
}
