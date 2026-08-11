<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Shared\Search\BooleanQueryFactory;
use App\Shared\Search\SearchPage;
use App\Shared\Search\SearchQuery;
use App\Shared\Search\SearchResult;
use App\Shared\Service\SearchEngineInterface;
use App\Pim\Repository\FicheSearchRepository;
use Symfony\Component\Uid\Ulid;

final readonly class MariaDbSearchEngine implements SearchEngineInterface
{
    public function __construct(private FicheSearchRepository $repository)
    {
    }

    public function search(SearchQuery $query): SearchPage
    {
        $text = trim($query->text);
        if ('' === $text) {
            return new SearchPage([], null, 0);
        }

        $booleanQuery = BooleanQueryFactory::fromText($text);
        $exactCode = ctype_digit($text) && strlen($text) <= 10 && (int) $text <= 4_294_967_295 ? (int) $text : null;
        // Aucun mot assez long pour l'index FULLTEXT : repli sur le libellé.
        $labelLike = '' === $booleanQuery && null === $exactCode ? BooleanQueryFactory::likePattern($text) : null;

        $cursorScore = null;
        $cursorId = null;
        if (null !== $query->cursor) {
            [$cursorScore, $cursorId] = $this->decodeCursor($query->cursor);
        }
        $search = $this->repository->search($booleanQuery, $exactCode, $query->filters, $query->limit, $cursorScore, $cursorId, $labelLike);
        $rows = $search['rows'];
        $hasNext = count($rows) > $query->limit;
        $rows = array_slice($rows, 0, $query->limit);
        $results = array_map(static fn (array $row): SearchResult => new SearchResult((string) Ulid::fromBinary($row['id']), (float) $row['score']), $rows);

        $last = [] === $rows ? null : $rows[array_key_last($rows)];

        return new SearchPage(
            $results,
            $hasNext && null !== $last ? $this->encodeCursor((float) $last['score'], Ulid::fromBinary($last['id'])) : null,
            $search['total'],
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
