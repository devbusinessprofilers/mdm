<?php

declare(strict_types=1);

namespace App\Pim\Repository;

use App\Pim\Entity\ReferentielExport;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ReferentielExport> */
class ReferentielExportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReferentielExport::class);
    }

    /**
     * Trace la demande dès le clic : visible « en attente » dans le journal
     * /outils avant même le passage du worker.
     *
     * @param list<string>         $colonnes
     * @param list<string>|null    $ids
     * @param array<string, mixed> $filtres
     */
    public function demarrer(string $demandeur, array $colonnes, ?array $ids, array $filtres, int $nb): ReferentielExport
    {
        $export = new ReferentielExport($demandeur, $colonnes, $ids, $filtres, $nb);
        $this->getEntityManager()->persist($export);
        $this->getEntityManager()->flush();

        return $export;
    }

    /** @return list<ReferentielExport> exports terminés dont la rétention est écoulée */
    public function aPurger(): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.statut = :statut')
            ->andWhere('e.expiresAt <= :maintenant')
            ->setParameter('statut', ReferentielExport::STATUT_TERMINEE)
            ->setParameter('maintenant', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }
}
