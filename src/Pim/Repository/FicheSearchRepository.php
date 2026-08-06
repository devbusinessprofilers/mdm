<?php

declare(strict_types=1);

namespace App\Pim\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\Uid\Ulid;

final readonly class FicheSearchRepository
{
    public function __construct(private Connection $connection) {}

    /**
     * @param array<string, mixed> $filters
     * @return array{rows: list<array{id: string, score: string|float|int}>, total: int}
     */
    public function search(
        string $booleanQuery,
        ?int $exactCode,
        array $filters,
        int $limit,
        ?string $cursorScore,
        ?Ulid $cursorId,
    ): array {
        $scoreParts = [];
        $conditions = [];
        $parameters = [];
        $types = [];
        if ('' !== $booleanQuery) {
            $scoreParts[] = 'MATCH (s.content) AGAINST (:query IN BOOLEAN MODE)';
            $conditions[] = 'MATCH (s.content) AGAINST (:query IN BOOLEAN MODE)';
            $parameters['query'] = $booleanQuery;
            $types['query'] = ParameterType::STRING;
        }
        if (null !== $exactCode) {
            $scoreParts[] = 'IF(f.code = :exactCode, 1000000, 0)';
            $conditions[] = 'f.code = :exactCode';
            $parameters['exactCode'] = $exactCode;
            $types['exactCode'] = ParameterType::INTEGER;
        }
        $joins = ['INNER JOIN pim_fiche f ON f.id = s.fiche_id'];
        $filterConditions = [];
        if (isset($filters['country'])) {
            if (!is_string($filters['country'])) {
                throw new \InvalidArgumentException('Invalid country search filter.');
            }
            $joins[] = 'INNER JOIN pim_localisation loc ON loc.id = f.localisation_id';
            $filterConditions[] = 'loc.country_code = :country';
            $parameters['country'] = $filters['country'];
            $types['country'] = ParameterType::STRING;
        }
        $completenessBounds = [];
        foreach (['completeness_min' => '>=', 'completeness_max' => '<='] as $bound => $operator) {
            if (isset($filters[$bound])) {
                if (!is_int($filters[$bound])) {
                    throw new \InvalidArgumentException(sprintf('Invalid %s search filter.', $bound));
                }
                $completenessBounds[$bound] = $operator;
                $parameters[$bound] = $filters[$bound];
                $types[$bound] = ParameterType::INTEGER;
            }
        }
        if ([] !== $completenessBounds) {
            $joins[] = "LEFT JOIN pim_lieu cl ON cl.fiche_id = f.id AND f.type = 'lieu'";
            $joins[] = "LEFT JOIN pim_activite ca ON ca.fiche_id = f.id AND f.type = 'activite'";
            $joins[] = "LEFT JOIN pim_restaurant cr ON cr.fiche_id = f.id AND f.type = 'restaurant'";
            $joins[] = "LEFT JOIN pim_service_evenementiel cs ON cs.fiche_id = f.id AND f.type = 'service_evenementiel'";
            foreach ($completenessBounds as $bound => $operator) {
                $filterConditions[] = sprintf(
                    'COALESCE(cl.completeness_global, ca.completeness_global, cr.completeness_global, cs.completeness_global, 0) %s :%s',
                    $operator,
                    $bound,
                );
            }
        }
        $fromWhereSql = sprintf(
            'FROM pim_fiche_search s %s WHERE (%s)',
            implode(' ', $joins),
            implode(' OR ', $conditions),
        );
        foreach ($filterConditions as $condition) {
            $fromWhereSql .= ' AND '.$condition;
        }
        foreach (['type', 'status'] as $filter) {
            if (isset($filters[$filter]) && is_string($filters[$filter])) {
                $fromWhereSql .= sprintf(' AND f.%s = :%s', $filter, $filter);
                $parameters[$filter] = $filters[$filter];
                $types[$filter] = ParameterType::STRING;
            } elseif (isset($filters[$filter]) && is_array($filters[$filter]) && [] !== $filters[$filter]) {
                $values = array_values(array_filter($filters[$filter], 'is_string'));
                if (count($values) !== count($filters[$filter])) {
                    throw new \InvalidArgumentException(sprintf('Invalid %s search filter.', $filter));
                }
                $fromWhereSql .= sprintf(' AND f.%s IN (:%s)', $filter, $filter);
                $parameters[$filter] = $values;
                $types[$filter] = ArrayParameterType::STRING;
            }
        }
        $total = (int) $this->connection->fetchOne('SELECT COUNT(*) '.$fromWhereSql, $parameters, $types);
        $sql = sprintf('SELECT f.id, %s AS score %s', implode(' + ', $scoreParts), $fromWhereSql);
        if (null !== $cursorScore && null !== $cursorId) {
            $sql .= ' HAVING (score < :cursorScore OR (score = :cursorScore AND f.id < :cursorId))';
            $parameters['cursorScore'] = $cursorScore;
            $parameters['cursorId'] = $cursorId->toBinary();
            $types['cursorScore'] = ParameterType::STRING;
            $types['cursorId'] = ParameterType::BINARY;
        }
        $sql .= ' ORDER BY score DESC, f.id DESC LIMIT '.(max(1, $limit) + 1);
        /** @var list<array{id: string, score: string|float|int}> $rows */
        $rows = $this->connection->fetchAllAssociative($sql, $parameters, $types);

        return ['rows' => $rows, 'total' => $total];
    }
}
