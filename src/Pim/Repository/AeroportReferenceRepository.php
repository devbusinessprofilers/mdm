<?php

declare(strict_types=1);

namespace App\Pim\Repository;

use App\Pim\Entity\AeroportReference;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AeroportReference> */
class AeroportReferenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AeroportReference::class);
    }

    /**
     * Aéroport le plus proche du point donné, dans le rayon (à vol d'oiseau).
     * Pré-filtre par boîte englobante pour rester sur l'index, puis haversine.
     *
     * @return array{nom: string, codeIata: ?string, latitude: float, longitude: float, distanceKm: float}|null
     */
    public function plusProche(float $latitude, float $longitude, float $rayonKm): ?array
    {
        $ligne = $this->getEntityManager()->getConnection()->fetchAssociative(
            <<<'SQL'
                SELECT nom, code_iata, latitude, longitude,
                       6371 * 2 * ASIN(SQRT(
                           POWER(SIN(RADIANS(latitude - :lat) / 2), 2)
                           + COS(RADIANS(:lat)) * COS(RADIANS(latitude))
                           * POWER(SIN(RADIANS(longitude - :lon) / 2), 2)
                       )) AS distance_km
                FROM pim_aeroport_reference
                WHERE latitude BETWEEN :latMin AND :latMax
                  AND longitude BETWEEN :lonMin AND :lonMax
                HAVING distance_km <= :rayon
                ORDER BY distance_km ASC
                LIMIT 1
                SQL,
            self::bornes($latitude, $longitude, $rayonKm) + ['lat' => $latitude, 'lon' => $longitude, 'rayon' => $rayonKm],
        );

        return false === $ligne ? null : [
            'nom' => (string) $ligne['nom'],
            'codeIata' => null === $ligne['code_iata'] ? null : (string) $ligne['code_iata'],
            'latitude' => (float) $ligne['latitude'],
            'longitude' => (float) $ligne['longitude'],
            'distanceKm' => (float) $ligne['distance_km'],
        ];
    }

    /**
     * Remplace tout le référentiel en une transaction : la table reste
     * servie si l'import échoue en route.
     *
     * @param list<array{nom: string, code_iata: ?string, code_pays: string, latitude: float, longitude: float}> $lignes
     */
    public function remplacer(array $lignes): void
    {
        $this->getEntityManager()->getConnection()->transactional(static function (Connection $connection) use ($lignes): void {
            $connection->executeStatement('DELETE FROM pim_aeroport_reference');
            foreach ($lignes as $ligne) {
                $connection->insert('pim_aeroport_reference', $ligne);
            }
        });
    }

    /**
     * Boîte englobante du rayon : 1° de latitude ≈ 111 km, 1° de longitude
     * rétrécit avec la latitude. Le plancher du cosinus évite la division par
     * zéro aux pôles (la boîte devient alors mondiale, le haversine tranche).
     *
     * @return array{latMin: float, latMax: float, lonMin: float, lonMax: float}
     */
    public static function bornes(float $latitude, float $longitude, float $rayonKm): array
    {
        $deltaLat = $rayonKm / 111.0;
        $deltaLon = $rayonKm / (111.0 * max(cos(deg2rad($latitude)), 0.01));

        return [
            'latMin' => $latitude - $deltaLat,
            'latMax' => $latitude + $deltaLat,
            'lonMin' => max($longitude - $deltaLon, -180.0),
            'lonMax' => min($longitude + $deltaLon, 180.0),
        ];
    }
}
