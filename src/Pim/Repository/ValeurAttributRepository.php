<?php

declare(strict_types=1);

namespace App\Pim\Repository;

use App\Pim\Entity\AttributDefinition;
use App\Pim\Entity\ValeurAttribut;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception\InvalidFieldNameException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ValeurAttribut> */
final class ValeurAttributRepository extends ServiceEntityRepository
{
    /** @var list<string>|null cache de listePrestataireLabels() */
    private ?array $prestataireLabels = null;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ValeurAttribut::class);
    }

    /** @return list<ValeurAttribut> */
    public function findOrderedByAttribute(AttributDefinition $attribute): array
    {
        return $this->findBy(['attribute' => $attribute], ['position' => 'ASC', 'id' => 'ASC']);
    }

    /**
     * Valeurs actives des attributs demandés, prêtes pour un champ de choix,
     * dans l'ordre de la liste de codes.
     *
     * @param list<string> $attributeCodes
     *
     * @return list<array{code: string, label: string, choices: array<string, int>}>
     */
    public function classificationChoices(array $attributeCodes): array
    {
        if ([] === $attributeCodes) {
            return [];
        }
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT ad.code AS attribut, ad.label AS attribut_label, av.id, av.label
             FROM pim_attribute_value av
             INNER JOIN pim_attribute_definition ad ON ad.id = av.attribute_id
             WHERE ad.code IN (:codes) AND av.active = 1
             ORDER BY av.position ASC, av.label ASC',
            ['codes' => $attributeCodes],
            ['codes' => \Doctrine\DBAL\ArrayParameterType::STRING],
        );
        $attributs = [];
        foreach ($rows as $row) {
            $code = (string) $row['attribut'];
            $attributs[$code] ??= ['code' => $code, 'label' => (string) $row['attribut_label'], 'choices' => []];
            $attributs[$code]['choices'][(string) $row['label']] = (int) $row['id'];
        }

        return array_values(array_filter(array_map(
            static fn (string $code): ?array => $attributs[$code] ?? null,
            $attributeCodes,
        )));
    }

    public function findOneForAttribute(AttributDefinition $attribute, int $id): ?ValeurAttribut
    {
        return $this->findOneBy(['attribute' => $attribute, 'id' => $id]);
    }

    public function findOneByAttributeAndCode(AttributDefinition $attribute, string $code): ?ValeurAttribut
    {
        return $this->findOneBy(['attribute' => $attribute, 'code' => $code]);
    }

    public function findPrestataireByCode(string $code): ?ValeurAttribut
    {
        return $this->createPrestataireQueryBuilder()
            ->andWhere('value.code = :valueCode')
            ->setParameter('valueCode', $code)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** Résolution par libellé (import en masse : le fichier d'export porte les libellés). */
    public function findPrestataireByLabel(string $label): ?ValeurAttribut
    {
        $resultats = $this->createPrestataireQueryBuilder()
            ->andWhere('LOWER(value.label) = LOWER(:valueLabel)')
            ->setParameter('valueLabel', trim($label))
            ->setMaxResults(2)
            ->getQuery()
            ->getResult();

        // Libellé ambigu (deux prestataires homonymes) : ne pas deviner.
        return 1 === count($resultats) ? $resultats[0] : null;
    }

    /**
     * Libellés prestataires pour les suggestions du rapport d'erreurs
     * d'import — mémoïsés : appelé au plus une fois par ligne en erreur, le
     * référentiel est stable pendant un import.
     *
     * @return list<string>
     */
    public function listePrestataireLabels(): array
    {
        return $this->prestataireLabels ??= array_values(array_map(
            strval(...),
            $this->createPrestataireQueryBuilder()
                ->select('value.label')
                ->getQuery()
                ->getSingleColumnResult(),
        ));
    }

    public function createPrestataireQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('value')
            ->innerJoin('value.attribute', 'attribute')
            ->andWhere('attribute.code = :attributeCode')
            ->setParameter('attributeCode', 'PRESTATAIRE')
            ->orderBy('value.label', 'ASC');
    }

    /** @return array{attribute_code: string, code: string}|null */
    public function findIdentityAt(int $id): ?array
    {
        $row = $this->getEntityManager()->getConnection()->fetchAssociative(
            'SELECT a.code attribute_code, v.code FROM pim_attribute_value v INNER JOIN pim_attribute_definition a ON a.id = v.attribute_id WHERE v.id = ?',
            [$id],
        );

        return false === $row ? null : ['attribute_code' => (string) $row['attribute_code'], 'code' => (string) $row['code']];
    }

    /** @return list<ValeurAttribut> */
    public function findOrdered(int $limit): array
    {
        return $this->findBy([], ['id' => 'ASC'], max(1, min(1000, $limit)));
    }

    /** @return list<array{attribute_code: string, id: int|string, code: string, label: string, active: int|string|bool}> */
    public function findRuntimeRows(): array
    {
        // Rechargé à chaque requête HTTP, chaque message worker et chaque
        // commande console : la tolérance au schéma pas encore migré passe par
        // les exceptions plutôt que par une introspection préalable — quatre
        // requêtes information_schema (~10 ms) économisées sur chaque hit.
        $connection = $this->getEntityManager()->getConnection();
        try {
            /** @var list<array{attribute_code: string, id: int|string, code: string, label: string, active: int|string|bool}> */
            return $connection->fetchAllAssociative(
                "SELECT a.code attribute_code, v.id, v.code, v.label, v.active active FROM pim_attribute_value v INNER JOIN pim_attribute_definition a ON a.id = v.attribute_id WHERE a.code <> 'PRESTATAIRE' ORDER BY a.code, v.position, v.id",
            );
        } catch (InvalidFieldNameException) {
            // Colonne active pas encore migrée : toutes les valeurs sont actives.
            try {
                /** @var list<array{attribute_code: string, id: int|string, code: string, label: string, active: int|string|bool}> */
                return $connection->fetchAllAssociative(
                    "SELECT a.code attribute_code, v.id, v.code, v.label, 1 active FROM pim_attribute_value v INNER JOIN pim_attribute_definition a ON a.id = v.attribute_id WHERE a.code <> 'PRESTATAIRE' ORDER BY a.code, v.position, v.id",
                );
            } catch (TableNotFoundException) {
                return [];
            }
        } catch (TableNotFoundException) {
            // Installation fraîche, tables pas encore créées.
            return [];
        }
    }

    /**
     * Lignes du dictionnaire pour la diffusion marketplace : toutes les
     * valeurs, désactivées comprises (une désactivation doit être poussée
     * pour dépublier la valeur côté marketplace).
     *
     * @return list<array{attribute_code: string, code: string, label: string, position: int|string, active: int|string|bool}>
     */
    public function findDictionaryRows(): array
    {
        /** @var list<array{attribute_code: string, code: string, label: string, position: int|string, active: int|string|bool}> */
        return $this->getEntityManager()->getConnection()->fetchAllAssociative(
            "SELECT a.code attribute_code, v.code, v.label, v.position, v.active
             FROM pim_attribute_value v
             INNER JOIN pim_attribute_definition a ON a.id = v.attribute_id
             WHERE a.code <> 'PRESTATAIRE'
             ORDER BY a.code, v.position, v.id",
        );
    }

    public function upsertPrestataire(int $id, int $attributeId, string $code, string $label, int $position): void
    {
        $this->getEntityManager()->getConnection()->executeStatement(
            'INSERT INTO pim_attribute_value (id, attribute_id, code, label, position) VALUES (:id, :attribute, :code, :label, :position) ON DUPLICATE KEY UPDATE label = VALUES(label), position = VALUES(position)',
            ['id' => $id, 'attribute' => $attributeId, 'code' => $code, 'label' => $label, 'position' => $position],
            ['id' => ParameterType::INTEGER, 'attribute' => ParameterType::INTEGER, 'code' => ParameterType::STRING, 'label' => ParameterType::STRING, 'position' => ParameterType::INTEGER],
        );
    }
}
