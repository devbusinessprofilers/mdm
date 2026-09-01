<?php

declare(strict_types=1);

namespace App\Pim\Repository;

use App\Pim\Entity\GrandeVilleReference;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<GrandeVilleReference> */
class GrandeVilleReferenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GrandeVilleReference::class);
    }

    /**
     * Remplace tout le référentiel en une transaction : la table reste
     * servie si l'import échoue en route.
     *
     * @param list<array{nom: string, code_pays: string, population: int, latitude: float, longitude: float}> $lignes
     */
    public function remplacer(array $lignes): void
    {
        $this->getEntityManager()->getConnection()->transactional(static function (Connection $connection) use ($lignes): void {
            $connection->executeStatement('DELETE FROM pim_grande_ville_reference');
            foreach ($lignes as $ligne) {
                $connection->insert('pim_grande_ville_reference', $ligne);
            }
        });
    }

    /**
     * Ville la plus proche du point donné parmi celles d'au moins
     * `populationMin` habitants, dans le rayon (à vol d'oiseau).
     *
     * @return array{nom: string, population: int, latitude: float, longitude: float, distanceKm: float}|null
     */
    public function plusProche(float $latitude, float $longitude, float $rayonKm, int $populationMin): ?array
    {
        $ligne = $this->getEntityManager()->getConnection()->fetchAssociative(
            <<<'SQL'
                SELECT nom, population, latitude, longitude,
                       6371 * 2 * ASIN(SQRT(
                           POWER(SIN(RADIANS(latitude - :lat) / 2), 2)
                           + COS(RADIANS(:lat)) * COS(RADIANS(latitude))
                           * POWER(SIN(RADIANS(longitude - :lon) / 2), 2)
                       )) AS distance_km
                FROM pim_grande_ville_reference
                WHERE population >= :populationMin
                  AND latitude BETWEEN :latMin AND :latMax
                  AND longitude BETWEEN :lonMin AND :lonMax
                HAVING distance_km <= :rayon
                ORDER BY distance_km ASC
                LIMIT 1
                SQL,
            AeroportReferenceRepository::bornes($latitude, $longitude, $rayonKm)
                + ['lat' => $latitude, 'lon' => $longitude, 'rayon' => $rayonKm, 'populationMin' => $populationMin],
        );

        return false === $ligne ? null : [
            'nom' => (string) $ligne['nom'],
            'population' => (int) $ligne['population'],
            'latitude' => (float) $ligne['latitude'],
            'longitude' => (float) $ligne['longitude'],
            'distanceKm' => (float) $ligne['distance_km'],
        ];
    }
}
