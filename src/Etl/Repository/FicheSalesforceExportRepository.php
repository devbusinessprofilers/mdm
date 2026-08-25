<?php

declare(strict_types=1);

namespace App\Etl\Repository;

use App\Etl\Entity\FicheSalesforceExport;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/** @extends ServiceEntityRepository<FicheSalesforceExport> */
class FicheSalesforceExportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FicheSalesforceExport::class);
    }

    public function forFiche(Ulid $ficheId): ?FicheSalesforceExport
    {
        return $this->find($ficheId);
    }

    /**
     * Fiches modifiées depuis le dernier envoi Produits (ou jamais envoyées),
     * hors lignes en backoff après échec (retryAt futur).
     *
     * @return list<FicheSalesforceExport>
     */
    public function dirtyProduits(int $limit): array
    {
        return array_values(
            $this->createQueryBuilder('e')
                ->andWhere('e.sentAt IS NULL OR e.sentAt < e.dirtyAt')
                ->andWhere('e.retryAt IS NULL OR e.retryAt <= :maintenant')
                ->setParameter('maintenant', new \DateTimeImmutable())
                ->orderBy('e.dirtyAt', 'ASC')
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult(),
        );
    }

    /**
     * Fiches modifiées depuis le dernier envoi Salles (ou jamais envoyées), en
     * données scalaires (id de fiche → échéance dirtyAt) : le traitement par
     * sous-lots fait des clear(), aucune entité ne doit y survivre.
     *
     * @return array<string, \DateTimeImmutable>
     */
    public function dirtySallesDonnees(int $limit): array
    {
        /** @var list<array{ficheId: Ulid, dirtyAt: \DateTimeImmutable}> $rows */
        $rows = $this->createQueryBuilder('e')
            ->select('e.ficheId AS ficheId', 'e.dirtyAt AS dirtyAt')
            ->andWhere('e.sallesSentAt IS NULL OR e.sallesSentAt < e.dirtyAt')
            ->orderBy('e.dirtyAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
        $donnees = [];
        foreach ($rows as $row) {
            $donnees[(string) $row['ficheId']] = $row['dirtyAt'];
        }

        return $donnees;
    }
}
