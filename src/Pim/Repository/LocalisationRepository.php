<?php

declare(strict_types=1);

namespace App\Pim\Repository;

use App\Pim\Entity\Localisation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Localisation> */
final class LocalisationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Localisation::class);
    }

    public function countOtherLocationsWithSameAddress(
        Localisation $location,
    ): int {
        if (null === $location->addressFingerprint()) {
            return 0;
        }

        return (int) $this->createQueryBuilder('location')
            ->select('COUNT(location.id)')
            ->where('location.addressFingerprint = :fingerprint')
            ->andWhere('location.id != :id')
            ->setParameter('fingerprint', $location->addressFingerprint())
            ->setParameter('id', $location->id(), 'ulid')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<string> */
    public function findDistinctCountryCodes(): array
    {
        /** @var list<string> $codes */
        $codes = $this->getEntityManager()->getConnection()->fetchFirstColumn(
            'SELECT DISTINCT country_code FROM pim_localisation WHERE country_code IS NOT NULL ORDER BY country_code',
        );

        return $codes;
    }

    /** @return list<string> codes postaux distincts renseignés (borne l'index DATAtourisme) */
    public function findDistinctPostalCodes(): array
    {
        /** @var list<string> $codes */
        $codes = $this->getEntityManager()->getConnection()->fetchFirstColumn(
            "SELECT DISTINCT code_postal FROM pim_localisation WHERE code_postal IS NOT NULL AND code_postal <> ''",
        );

        return $codes;
    }
}
