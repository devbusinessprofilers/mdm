<?php

declare(strict_types=1);

namespace App\Account\Repository;

use App\Account\Entity\AccountInvitation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AccountInvitation> */
final class AccountInvitationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, AccountInvitation::class); }
}
