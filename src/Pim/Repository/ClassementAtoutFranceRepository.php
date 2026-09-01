<?php

declare(strict_types=1);

namespace App\Pim\Repository;

use App\Pim\Entity\ClassementAtoutFrance;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ClassementAtoutFrance> */
class ClassementAtoutFranceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClassementAtoutFrance::class);
    }

    /**
     * Remplace tout le référentiel en une transaction : la table reste
     * servie si l'import échoue en route.
     *
     * @param list<array{nom: string, code_postal: string, commune: string, type_etablissement: string, etoiles: int, nombre_chambres: ?int, date_classement: ?string}> $lignes
     */
    public function remplacer(array $lignes): void
    {
        $this->getEntityManager()->getConnection()->transactional(static function (Connection $connection) use ($lignes): void {
            $connection->executeStatement('DELETE FROM pim_classement_atout_france');
            foreach ($lignes as $ligne) {
                $connection->insert('pim_classement_atout_france', $ligne);
            }
        });
    }

    /**
     * Candidats au rapprochement par nom d'une fiche du code postal donné.
     *
     * @return list<array{nom: string, typeEtablissement: string, etoiles: int, nombreChambres: ?int}>
     */
    public function parCodePostal(string $codePostal): array
    {
        $lignes = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT nom, type_etablissement, etoiles, nombre_chambres FROM pim_classement_atout_france WHERE code_postal = :codePostal',
            ['codePostal' => $codePostal],
        );

        return array_map(static fn (array $ligne): array => [
            'nom' => (string) $ligne['nom'],
            'typeEtablissement' => (string) $ligne['type_etablissement'],
            'etoiles' => (int) $ligne['etoiles'],
            'nombreChambres' => null === $ligne['nombre_chambres'] ? null : (int) $ligne['nombre_chambres'],
        ], $lignes);
    }
}
