<?php

declare(strict_types=1);

namespace App\Pim\Repository;

use App\Pim\Entity\FicheCollaborateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<FicheCollaborateur> */
final class FicheCollaborateurRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, FicheCollaborateur::class); }
    public function findOneByEmail(string $email): ?FicheCollaborateur { return $this->findOneBy(['email' => FicheCollaborateur::normalizeEmail($email)]); }
}
