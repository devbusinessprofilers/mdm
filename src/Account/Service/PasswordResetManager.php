<?php

declare(strict_types=1);

namespace App\Account\Service;

use App\Account\Entity\PasswordResetRequest;
use App\Account\Entity\User;
use App\Account\Message\InternalUserPasswordResetRequested;
use App\Account\Repository\AccountInvitationRepository;
use App\Account\Repository\PasswordResetRequestRepository;
use App\Shared\Outbox\OutboxPublisherInterface;
use App\Shared\Service\ParametreProviderInterface;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[WithMonologChannel('mail')]
final readonly class PasswordResetManager
{
    public function __construct(
        private PasswordResetRequestRepository $requests,
        private AccountInvitationRepository $invitations,
        private EntityManagerInterface $entityManager,
        private OutboxPublisherInterface $outbox,
        private UserPasswordHasherInterface $hasher,
        private ParametreProviderInterface $parametres,
        private LoggerInterface $logger,
    ) {}

    public function request(User $user): PasswordResetRequest
    {
        if ($user->isDeleted()) {
            throw new \DomainException('Ce compte a été supprimé.');
        }
        if (null === $user->getPassword()) {
            throw new \DomainException('Ce compte doit d’abord être activé.');
        }

        $request = $this->entityManager->wrapInTransaction(function () use ($user): PasswordResetRequest {
            $this->entityManager->lock($user, LockMode::PESSIMISTIC_WRITE);
            $this->requests->invalidateUsableFor($user);
            $request = new PasswordResetRequest($user, $this->parametres->int('compte.reset_validite_heures'));
            $this->entityManager->persist($request);
            $this->outbox->enqueue(new InternalUserPasswordResetRequested($request->id()));

            return $request;
        });
        $this->logger->info('account.password_reset.requested', ['user_id' => $user->id()]);

        return $request;
    }

    /** Changement volontaire par l'utilisateur connecté : lève aussi l'obligation posée par un admin. */
    public function changeOwnPassword(User $user, string $plainPassword): void
    {
        $this->entityManager->wrapInTransaction(function () use ($user, $plainPassword): void {
            $this->entityManager->lock($user, LockMode::PESSIMISTIC_WRITE);
            $user->setPassword($this->hasher->hashPassword($user, $plainPassword));
            $this->requests->invalidateUsableFor($user);
            $this->invitations->invalidateUsableFor($user);
        });
        $this->logger->info('account.password_change.completed', ['user_id' => $user->id()]);
    }

    public function reset(PasswordResetRequest $request, string $plainPassword): void
    {
        if (!$request->isUsable()) {
            throw new \DomainException('Cette demande de réinitialisation n’est plus utilisable.');
        }

        $user = $request->user();
        $this->entityManager->wrapInTransaction(function () use ($request, $user, $plainPassword): void {
            $this->entityManager->lock($user, LockMode::PESSIMISTIC_WRITE);
            $request->use();
            $user->setPassword($this->hasher->hashPassword($user, $plainPassword));
            $this->requests->invalidateUsableFor($user, $request->id());
            $this->invitations->invalidateUsableFor($user);
        });
        $this->logger->info('account.password_reset.completed', ['user_id' => $user->id()]);
    }
}
