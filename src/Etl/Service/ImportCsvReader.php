<?php

declare(strict_types=1);

namespace App\Etl\Service;

use App\Pim\Import\Dto\RawCsvRow;
use App\Pim\Import\FicheImportTemplateGenerator;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;

/**
 * Lit les fichiers d'import de fiches : XLSX (première feuille par défaut,
 * ou une feuille nommée — l'import en masse lit la feuille de sa gamme) ou
 * CSV. Les numéros de lignes sont des numéros d'enregistrement (1 = en-têtes) :
 * en CSV, un champ quoté multiligne compte pour un seul enregistrement, le
 * numéro peut donc différer du numéro de ligne physique du fichier. Les
 * lignes vides et celles commençant par # sont ignorées.
 */
final class ImportCsvReader
{
    /** @return list<string> en-têtes de la ligne 1 */
    public function headers(string $path, ?string $sheet = null): array
    {
        if (self::isXlsx($path)) {
            foreach ($this->xlsxCells($path, $sheet) as $cells) {
                return array_map(static fn (string $cell): string => trim($cell), $cells);
            }

            throw new \RuntimeException('Impossible de lire la ligne d’en-têtes.');
        }

        $handle = $this->open($path);

        try {
            $cells = fgetcsv($handle, null, FicheImportTemplateGenerator::SEPARATOR, '"', '');
            if (!is_array($cells)) {
                throw new \RuntimeException('Impossible de lire la ligne d’en-têtes.');
            }

            $headers = array_map(static fn (?string $cell): string => trim((string) $cell), $cells);
            $headers[0] = ltrim($headers[0], "\u{FEFF}");

            return $headers;
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param list<string> $headers
     *
     * @return \Generator<RawCsvRow>
     */
    public function rows(string $path, array $headers, int $fromLine, ?string $sheet = null): \Generator
    {
        return self::isXlsx($path) ? $this->xlsxRows($path, $headers, $fromLine, $sheet) : $this->csvRows($path, $headers, $fromLine);
    }

    /** @param list<string> $headers */
    public function countDataLines(string $path, array $headers, ?string $sheet = null): int
    {
        $count = 0;
        foreach ($this->rows($path, $headers, 2, $sheet) as $_row) {
            ++$count;
        }

        return $count;
    }

    /**
     * @param list<string> $headers
     *
     * @return \Generator<RawCsvRow>
     */
    private function xlsxRows(string $path, array $headers, int $fromLine, ?string $sheet = null): \Generator
    {
        foreach ($this->xlsxCells($path, $sheet) as $lineNumber => $cells) {
            if ($lineNumber < max(2, $fromLine) || !self::isDataCells($cells)) {
                continue;
            }

            yield new RawCsvRow($lineNumber, self::combine($headers, $cells));
        }
    }

    /**
     * Cellules normalisées en chaînes, indexées par numéro de ligne physique :
     * la première feuille par défaut, ou la feuille nommée demandée.
     *
     * @return \Generator<int, list<string>>
     */
    private function xlsxCells(string $path, ?string $sheetName = null): \Generator
    {
        if (!is_readable($path)) {
            throw new \RuntimeException('Fichier d’import illisible.');
        }

        $reader = new Reader(new Options(SHOULD_PRESERVE_EMPTY_ROWS: true));

        try {
            $reader->open($path);
        } catch (\Throwable) {
            throw new \RuntimeException('Fichier d’import illisible.');
        }

        try {
            $trouvee = false;
            foreach ($reader->getSheetIterator() as $sheet) {
                if (null !== $sheetName && $sheet->getName() !== $sheetName) {
                    continue;
                }
                $trouvee = true;
                foreach ($sheet->getRowIterator() as $lineNumber => $row) {
                    yield $lineNumber => array_values(array_map(self::stringify(...), $row->toArray()));
                }

                // Une seule feuille par lecture (« Données », ou la gamme demandée).
                break;
            }
            if (!$trouvee) {
                throw new \RuntimeException(sprintf('Feuille « %s » introuvable dans le classeur.', (string) $sheetName));
            }
        } finally {
            $reader->close();
        }
    }

    /**
     * @param list<string> $headers
     *
     * @return \Generator<RawCsvRow>
     */
    private function csvRows(string $path, array $headers, int $fromLine): \Generator
    {
        $handle = $this->open($path);

        try {
            $lineNumber = 0;
            while (false !== ($cells = fgetcsv($handle, null, FicheImportTemplateGenerator::SEPARATOR, '"', ''))) {
                ++$lineNumber;
                if ($lineNumber < max(2, $fromLine)) {
                    continue;
                }

                $values = array_map(static fn (?string $cell): string => (string) $cell, $cells);
                if (!self::isDataCells($values)) {
                    continue;
                }

                yield new RawCsvRow($lineNumber, self::combine($headers, $values));
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param list<string> $headers
     * @param list<string> $values
     *
     * @return array<string, string>
     */
    private static function combine(array $headers, array $values): array
    {
        $count = count($headers);

        return array_combine($headers, array_slice(array_pad($values, $count, ''), 0, $count));
    }

    /** @param list<string> $cells */
    private static function isDataCells(array $cells): bool
    {
        $first = ltrim(trim($cells[0] ?? ''), "\u{FEFF}");
        if (str_starts_with($first, FicheImportTemplateGenerator::HELP_PREFIX)) {
            return false;
        }

        foreach ($cells as $cell) {
            if ('' !== trim($cell)) {
                return true;
            }
        }

        return false;
    }

    private static function stringify(mixed $value): string
    {
        if (null === $value) {
            return '';
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if ($value instanceof \DateInterval) {
            return $value->format('%H:%I');
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_float($value)) {
            if (floor($value) === $value && abs($value) < PHP_INT_MAX) {
                return (string) (int) $value;
            }

            return rtrim(rtrim(number_format($value, 10, '.', ''), '0'), '.');
        }
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_string($value)) {
            return $value;
        }

        throw new \RuntimeException('Cellule de type non pris en charge dans la feuille de données.');
    }

    private static function isXlsx(string $path): bool
    {
        return str_ends_with(strtolower($path), '.xlsx');
    }

    /** @return resource */
    private function open(string $path)
    {
        $handle = @fopen($path, 'rb');
        if (false === $handle) {
            throw new \RuntimeException('Fichier d’import illisible.');
        }

        return $handle;
    }
}
