<?php

declare(strict_types=1);

namespace App\Pim\Repository;

use App\Pim\Entity\AttributDefinition;
use App\Pim\Entity\ValeurAttribut;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ValeurAttribut> */
final class ValeurAttributRepository extends ServiceEntityRepository
{
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
        $connection = $this->getEntityManager()->getConnection();
        $schema = $connection->createSchemaManager();
        if (!$schema->tablesExist(['pim_attribute_value', 'pim_attribute_definition'])) {
            return [];
        }
        $hasActive = isset($schema->listTableColumns('pim_attribute_value')['active']);

        /** @var list<array{attribute_code: string, id: int|string, code: string, label: string, active: int|string|bool}> $rows */
        $rows = $connection->fetchAllAssociative(sprintf(
            "SELECT a.code attribute_code, v.id, v.code, v.label, %s active FROM pim_attribute_value v INNER JOIN pim_attribute_definition a ON a.id = v.attribute_id WHERE a.code <> 'PRESTATAIRE' ORDER BY a.code, v.position, v.id",
            $hasActive ? 'v.active' : '1',
        ));

        return $rows;
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
