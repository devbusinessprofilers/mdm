<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Shared\Search\SearchPage;
use App\Shared\Search\SearchQuery;
use App\Shared\Search\SearchResult;
use App\Shared\Service\SearchEngineInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\Uid\Ulid;

final readonly class MariaDbSearchEngine implements SearchEngineInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function search(SearchQuery $query): SearchPage
    {
        $text = trim($query->text);
        if ('' === $text) {
            return new SearchPage([], null, 0);
        }

        $tokens = preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $tokens = false === $tokens ? [] : array_values(array_unique($tokens));
        $booleanQuery = implode(' ', array_map(static fn (string $token): string => '+'.$token.'*', $tokens));
        $exactCode = ctype_digit($text) && strlen($text) <= 10 && (int) $text <= 4_294_967_295 ? (int) $text : null;
        if ('' === $booleanQuery && null === $exactCode) {
            return new SearchPage([], null, 0);
        }

        $scoreParts = [];
        $searchConditions = [];
        $parameters = [];
        $types = [];
        if ('' !== $booleanQuery) {
            $scoreParts[] = 'MATCH (s.content) AGAINST (:query IN BOOLEAN MODE)';
            $searchConditions[] = 'MATCH (s.content) AGAINST (:query IN BOOLEAN MODE)';
            $parameters['query'] = $booleanQuery;
            $types['query'] = ParameterType::STRING;
        }
        if (null !== $exactCode) {
            $scoreParts[] = 'IF(f.code = :exactCode, 1000000, 0)';
            $searchConditions[] = 'f.code = :exactCode';
            $parameters['exactCode'] = $exactCode;
            $types['exactCode'] = ParameterType::INTEGER;
        }

        $fromWhereSql = sprintf(
            'FROM pim_fiche_search s INNER JOIN pim_fiche f ON f.id = s.fiche_id
            WHERE (%s)',
            implode(' OR ', $searchConditions),
        );
        foreach (['type', 'status'] as $filter) {
            if (isset($query->filters[$filter]) && is_string($query->filters[$filter])) {
                $fromWhereSql .= sprintf(' AND f.%s = :%s', $filter, $filter);
                $parameters[$filter] = $query->filters[$filter];
                $types[$filter] = ParameterType::STRING;
            }
        }

        $totalCount = (int) $this->connection->fetchOne('SELECT COUNT(*) '.$fromWhereSql, $parameters, $types);
        $sql = sprintf('SELECT f.id, %s AS score %s', implode(' + ', $scoreParts), $fromWhereSql);

        if (null !== $query->cursor) {
            [$cursorScore, $cursorId] = $this->decodeCursor($query->cursor);
            $sql .= ' HAVING (score < :cursorScore OR (score = :cursorScore AND f.id < :cursorId))';
            $parameters['cursorScore'] = $cursorScore;
            $parameters['cursorId'] = $cursorId->toBinary();
            $types['cursorScore'] = ParameterType::STRING;
            $types['cursorId'] = ParameterType::BINARY;
        }
        $sql .= ' ORDER BY score DESC, f.id DESC LIMIT '.($query->limit + 1);

        /** @var list<array{id: string, score: string|float|int}> $rows */
        $rows = $this->connection->fetchAllAssociative($sql, $parameters, $types);
        $hasNext = count($rows) > $query->limit;
        $rows = array_slice($rows, 0, $query->limit);
        $results = array_map(static fn (array $row): SearchResult => new SearchResult((string) Ulid::fromBinary($row['id']), (float) $row['score']), $rows);

        $last = [] === $rows ? null : $rows[array_key_last($rows)];

        return new SearchPage(
            $results,
            $hasNext && null !== $last ? $this->encodeCursor((float) $last['score'], Ulid::fromBinary($last['id'])) : null,
            $totalCount,
        );
    }

    private function encodeCursor(float $score, Ulid $id): string
    {
        $payload = json_encode([sprintf('%.17g', $score), (string) $id], JSON_THROW_ON_ERROR);

        return rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
    }

    /** @return array{numeric-string, Ulid} */
    private function decodeCursor(string $cursor): array
    {
        $decoded = base64_decode(strtr($cursor, '-_', '+/'), true);
        if (false === $decoded) {
            throw new \InvalidArgumentException('Invalid search cursor.');
        }

        try {
            $payload = json_decode($decoded, true, flags: JSON_THROW_ON_ERROR);
            if (!is_array($payload) || 2 !== count($payload) || !is_string($payload[0]) || !is_numeric($payload[0]) || !is_string($payload[1])) {
                throw new \InvalidArgumentException('Invalid search cursor.');
            }

            return [$payload[0], Ulid::fromString($payload[1])];
        } catch (\JsonException|\InvalidArgumentException $exception) {
            throw new \InvalidArgumentException('Invalid search cursor.', previous: $exception);
        }
    }
}
