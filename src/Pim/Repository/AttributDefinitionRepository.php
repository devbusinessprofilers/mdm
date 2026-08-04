<?php

declare(strict_types=1);

namespace App\Pim\Repository;

use App\Pim\Entity\AttributDefinition;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AttributDefinition> */
final class AttributDefinitionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AttributDefinition::class);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAdminPage(string $query, int $limit, int $offset): array
    {
        $params = ['limit' => $limit, 'offset' => $offset];
        $types = ['limit' => ParameterType::INTEGER, 'offset' => ParameterType::INTEGER];
        $where = '';
        if ('' !== $query) {
            $where = ' WHERE a.code LIKE :query OR a.label LIKE :query';
            $params['query'] = '%'.$query.'%';
            $types['query'] = ParameterType::STRING;
        }

        return $this->getEntityManager()->getConnection()->fetchAllAssociative(<<<SQL
            SELECT a.id, a.code, a.label, a.translatable,
                   COUNT(DISTINCT v.id) value_count,
                   MAX(CASE WHEN dt.locale = 'en' THEN dt.translated_label END) translation_en,
                   MAX(CASE WHEN dt.locale = 'es' THEN dt.translated_label END) translation_es,
                   MAX(CASE WHEN dt.locale = 'it' THEN dt.translated_label END) translation_it,
                   MAX(CASE WHEN dt.locale = 'nl' THEN dt.translated_label END) translation_nl,
                   MAX(CASE WHEN dt.locale = 'pt' THEN dt.translated_label END) translation_pt,
                   MAX(CASE WHEN dt.locale = 'de' THEN dt.translated_label END) translation_de
            FROM pim_attribute_definition a
            LEFT JOIN pim_attribute_value v ON v.attribute_id = a.id
            LEFT JOIN pim_attribute_definition_translation dt ON dt.attribute_id = a.id
            {$where}
            GROUP BY a.id, a.code, a.label, a.translatable
            ORDER BY a.code
            LIMIT :limit OFFSET :offset
            SQL, $params, $types);
    }

    public function findOneByCode(string $code): ?AttributDefinition
    {
        return $this->findOneBy(['code' => $code]);
    }

    /** @return list<AttributDefinition> */
    public function findOrdered(int $limit): array
    {
        return $this->findBy([], ['code' => 'ASC'], max(1, min(1000, $limit)));
    }
}
