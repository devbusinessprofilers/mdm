<?php

declare(strict_types=1);

namespace App\Account\Service;

use App\Account\Entity\AccountInvitation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class AccountInvitationManager
{
    public function __construct(private EntityManagerInterface $entityManager, private UserPasswordHasherInterface $hasher) {}

    public function accept(AccountInvitation $invitation, string $password): void
    {
        $user = $invitation->user();
        $user->setPassword($this->hasher->hashPassword($user, $password));
        $user->activate();
        $invitation->accept();
        $this->entityManager->flush();
    }
}
