<?php

declare(strict_types=1);

namespace App\Pim\Repository;

use App\Pim\Entity\Fiche;
use App\Pim\Entity\FicheEnrichmentRun;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<FicheEnrichmentRun> */
final class FicheEnrichmentRunRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FicheEnrichmentRun::class);
    }

    /** Trace la demande au clic : la ligne apparaît « en file » dans /outils avant même le passage du worker. */
    public function demarrer(Fiche $fiche): FicheEnrichmentRun
    {
        $run = new FicheEnrichmentRun($fiche);
        $this->getEntityManager()->persist($run);
        $this->getEntityManager()->flush();

        return $run;
    }

    /** Demande la plus ancienne non traitée d'une fiche (repli quand le message ne porte pas d'id de run). */
    public function plusAncienneEnAttente(Fiche $fiche): ?FicheEnrichmentRun
    {
        return $this->findOneBy(['fiche' => $fiche, 'finishedAt' => null], ['requestedAt' => 'ASC']);
    }
}
