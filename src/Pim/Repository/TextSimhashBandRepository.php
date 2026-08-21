<?php

declare(strict_types=1);

namespace App\Pim\Repository;

use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Ulid;

/**
 * Indexation LSH du SimHash 64 bits en 9 bandes, pour ramener la recherche de
 * quasi-doublons à un balayage de candidats. Copie texte de
 * App\Dam\Repository\MediaPerceptualHashBandRepository.
 */
final readonly class TextSimhashBandRepository
{
    private const BAND_LENGTHS = [8, 7, 7, 7, 7, 7, 7, 7, 7];

    public function __construct(private Connection $connection) {}

    public function replace(string $fingerprintId, string $hash): void
    {
        $binaryId = Ulid::fromString($fingerprintId)->toBinary();
        $this->connection->delete('pim_text_simhash_band', ['fingerprint_id' => $binaryId]);
        foreach ($this->bands($hash) as $index => $value) {
            $this->connection->insert('pim_text_simhash_band', [
                'fingerprint_id' => $binaryId,
                'band_index' => $index,
                'band_value' => $value,
            ]);
        }
    }

    public function deleteForFingerprint(string $fingerprintId): void
    {
        $this->connection->delete('pim_text_simhash_band', [
            'fingerprint_id' => Ulid::fromString($fingerprintId)->toBinary(),
        ]);
    }

    /** @return list<string> */
    public function candidateIds(string $hash, string $excludedFingerprintId, int $limit = 5000): array
    {
        $clauses = [];
        $parameters = ['excluded' => Ulid::fromString($excludedFingerprintId)->toBinary()];
        foreach ($this->bands($hash) as $index => $value) {
            $clauses[] = sprintf('(band_index = :band_%d AND band_value = :value_%d)', $index, $index);
            $parameters['band_'.$index] = $index;
            $parameters['value_'.$index] = $value;
        }
        $limit = max(1, min(10000, $limit));
        $rows = $this->connection->fetchFirstColumn(sprintf(
            'SELECT DISTINCT fingerprint_id FROM pim_text_simhash_band WHERE fingerprint_id <> :excluded AND (%s) LIMIT %d',
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
            throw new \InvalidArgumentException('SimHash hexadécimal de 64 bits attendu.');
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
