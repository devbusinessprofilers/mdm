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
     * Fiches modifiées depuis le dernier envoi Produits (ou jamais envoyées).
     *
     * @return list<FicheSalesforceExport>
     */
    public function dirtyProduits(int $limit): array
    {
        return $this->dirtyDepuis('sentAt', $limit);
    }

    /**
     * Fiches modifiées depuis le dernier envoi Salles (ou jamais envoyées).
     *
     * @return list<FicheSalesforceExport>
     */
    public function dirtySalles(int $limit): array
    {
        return $this->dirtyDepuis('sallesSentAt', $limit);
    }

    /** @return list<FicheSalesforceExport> */
    private function dirtyDepuis(string $champEnvoi, int $limit): array
    {
        return array_values(
            $this->createQueryBuilder('e')
                ->andWhere(sprintf('e.%1$s IS NULL OR e.%1$s < e.dirtyAt', $champEnvoi))
                ->orderBy('e.dirtyAt', 'ASC')
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult(),
        );
    }
}
