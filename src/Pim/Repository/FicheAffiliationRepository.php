<?php

declare(strict_types=1);

namespace App\Pim\Repository;

use App\Pim\Entity\Fiche;
use App\Pim\Entity\FicheAffiliation;
use App\Pim\Entity\FicheCollaborateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<FicheAffiliation> */
final class FicheAffiliationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, FicheAffiliation::class); }
    public function findFor(FicheCollaborateur $collaborateur, Fiche $fiche): ?FicheAffiliation { return $this->findOneBy(['collaborateur' => $collaborateur, 'fiche' => $fiche]); }
    public function countRequestRecipients(Fiche $fiche, ?FicheAffiliation $excluding = null): int
    {
        $sql = 'SELECT COUNT(*) FROM pim_fiche_affiliation WHERE fiche_id = :fiche AND receives_requests = 1';
        $parameters = ['fiche' => $fiche->id()]; $types = ['fiche' => 'ulid'];
        if (null !== $excluding) { $sql .= ' AND id != :excluding'; $parameters['excluding'] = $excluding->id(); $types['excluding'] = 'ulid'; }
        return (int) $this->getEntityManager()->getConnection()->fetchOne($sql, $parameters, $types);
    }
}
