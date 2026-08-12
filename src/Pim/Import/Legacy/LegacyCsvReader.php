<?php

declare(strict_types=1);

namespace App\Pim\Import\Legacy;

use App\Pim\Import\Dto\RawCsvRow;

/**
 * Lecteur du CSV d'export production : délimité par des virgules, champs
 * quotés multilignes (fgetcsv les gère nativement), en-têtes en ligne 1.
 * L'ImportCsvReader de l'ETL web n'est pas réutilisable (séparateur ';').
 */
final class LegacyCsvReader
{
    /** @return list<string> */
    public function headers(string $path): array
    {
        $handle = $this->open($path);
        try {
            $headers = fgetcsv($handle, null, ',', '"', '');
            if (!is_array($headers)) {
                throw new \RuntimeException('Impossible de lire les en-têtes du CSV.');
            }
            $headers[0] = ltrim((string) $headers[0], "\u{FEFF}");

            return array_map(strval(...), $headers);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param list<string> $headers
     * @return \Generator<LegacyCsvRecord> numérotés à partir de 1 (première ligne de données)
     */
    public function records(string $path, array $headers): \Generator
    {
        $expected = count($headers);
        $handle = $this->open($path);
        try {
            fgetcsv($handle, null, ',', '"', '');
            $recordNumber = 0;
            while (false !== ($cells = fgetcsv($handle, null, ',', '"', ''))) {
                if ([null] === $cells) {
                    continue;
                }
                ++$recordNumber;
                if (count($cells) !== $expected) {
                    yield new LegacyCsvRecord($recordNumber, null, sprintf('Nombre de colonnes inattendu (%d au lieu de %d).', count($cells), $expected));
                    continue;
                }
                yield new LegacyCsvRecord($recordNumber, new RawCsvRow($recordNumber, array_combine($headers, array_map(strval(...), $cells))));
            }
        } finally {
            fclose($handle);
        }
    }

    /** @return resource */
    private function open(string $path)
    {
        $handle = @fopen($path, 'rb');
        if (false === $handle) {
            throw new \RuntimeException(sprintf('Fichier CSV illisible : %s', $path));
        }

        return $handle;
    }
}
