<?php

declare(strict_types=1);

namespace App\Dam\Repository;

use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Ulid;

final readonly class MediaPerceptualHashBandRepository
{
    private const BAND_LENGTHS = [8, 7, 7, 7, 7, 7, 7, 7, 7];

    public function __construct(private Connection $connection) {}

    public function replace(string $mediaId, string $hash): void
    {
        $binaryId = Ulid::fromString($mediaId)->toBinary();
        $this->connection->delete('dam_media_phash_band', ['media_id' => $binaryId]);
        foreach ($this->bands($hash) as $index => $value) {
            $this->connection->insert('dam_media_phash_band', [
                'media_id' => $binaryId,
                'band_index' => $index,
                'band_value' => $value,
            ]);
        }
    }

    /** @return list<string> */
    public function candidateIds(string $hash, string $excludedMediaId, int $limit = 5000): array
    {
        $clauses = [];
        $parameters = ['excluded' => Ulid::fromString($excludedMediaId)->toBinary()];
        foreach ($this->bands($hash) as $index => $value) {
            $clauses[] = sprintf('(band_index = :band_%d AND band_value = :value_%d)', $index, $index);
            $parameters['band_'.$index] = $index;
            $parameters['value_'.$index] = $value;
        }
        $limit = max(1, min(10000, $limit));
        $rows = $this->connection->fetchFirstColumn(sprintf(
            'SELECT DISTINCT media_id FROM dam_media_phash_band WHERE media_id <> :excluded AND (%s) LIMIT %d',
            implode(' OR ', $clauses),
            $limit,
        ), $parameters);

        return array_map(
            static fn (mixed $id): string => (string) Ulid::fromBinary((string) $id),
            $rows,
        );
    }

    /** @return array<int, int> */
    public function bands(string $hash): array
    {
        if (1 !== preg_match('/^[0-9a-f]{16}$/i', $hash)) {
            throw new \InvalidArgumentException('pHash hexadecimal de 64 bits attendu.');
        }
        $bits = '';
        foreach (str_split(strtolower($hash)) as $digit) {
            $bits .= str_pad(decbin((int) hexdec($digit)), 4, '0', STR_PAD_LEFT);
        }
        $bands = [];
        $offset = 0;
        foreach (self::BAND_LENGTHS as $index => $length) {
            $bands[$index] = (int) bindec(substr($bits, $offset, $length));
            $offset += $length;
        }

        return $bands;
    }
}
