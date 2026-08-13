<?php

declare(strict_types=1);

namespace App\Etl\Repository;

use App\Etl\Entity\FicheSalesforce;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/** @extends ServiceEntityRepository<FicheSalesforce> */
class FicheSalesforceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FicheSalesforce::class);
    }

    public function forFiche(Ulid $ficheId): ?FicheSalesforce
    {
        return $this->find($ficheId);
    }

    /** La fiche est-elle connue de Salesforce (partenaireBp géré par le refresh) ? */
    public function existePourFiche(Ulid $ficheId): bool
    {
        return null !== $this->find($ficheId);
    }
}
