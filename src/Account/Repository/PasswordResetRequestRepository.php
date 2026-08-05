<?php

declare(strict_types=1);

namespace App\Account\Repository;

use App\Account\Entity\PasswordResetRequest;
use App\Account\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<PasswordResetRequest> */
final class PasswordResetRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PasswordResetRequest::class);
    }

    public function invalidateUsableFor(User $user, ?string $exceptId = null): int
    {
        $builder = $this->createQueryBuilder('reset')
            ->update()
            ->set('reset.invalidatedAt', ':now')
            ->andWhere('reset.user = :user')
            ->andWhere('reset.usedAt IS NULL')
            ->andWhere('reset.invalidatedAt IS NULL')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('user', $user);
        if (null !== $exceptId) {
            $builder->andWhere('reset.id != :exceptId')->setParameter('exceptId', $exceptId);
        }

        return $builder->getQuery()->execute();
    }

    public function deleteExpiredBefore(\DateTimeImmutable $before): int
    {
        return $this->createQueryBuilder('reset')
            ->delete()
            ->andWhere('reset.expiresAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();
    }
}
