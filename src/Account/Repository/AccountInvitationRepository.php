<?php

declare(strict_types=1);

namespace App\Account\Repository;

use App\Account\Entity\AccountInvitation;
use App\Account\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AccountInvitation> */
final class AccountInvitationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AccountInvitation::class);
    }

    public function invalidateUsableFor(User $user, ?string $exceptId = null): int
    {
        $builder = $this->createQueryBuilder('invitation')
            ->update()
            ->set('invitation.invalidatedAt', ':now')
            ->andWhere('invitation.user = :user')
            ->andWhere('invitation.acceptedAt IS NULL')
            ->andWhere('invitation.invalidatedAt IS NULL')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('user', $user);
        if (null !== $exceptId) {
            $builder->andWhere('invitation.id != :exceptId')->setParameter('exceptId', $exceptId);
        }

        return $builder->getQuery()->execute();
    }

    public function deleteExpiredBefore(\DateTimeImmutable $before): int
    {
        return $this->createQueryBuilder('invitation')
            ->delete()
            ->andWhere('invitation.expiresAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();
    }
}
