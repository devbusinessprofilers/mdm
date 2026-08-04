<?php

declare(strict_types=1);

namespace App\Etl\Service;

use App\Pim\Import\Dto\RawCsvRow;
use App\Pim\Import\FicheImportTemplateGenerator;

final class ImportCsvReader
{
    /** @return list<string> en-têtes de la ligne 1, BOM UTF-8 retiré */
    public function headers(string $path): array
    {
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
     * Lignes de données à partir de $fromLine (numéro physique, 1 = en-têtes) ;
     * les lignes vides et celles commençant par # sont ignorées.
     *
     * @param list<string> $headers
     *
     * @return \Generator<RawCsvRow>
     */
    public function rows(string $path, array $headers, int $fromLine): \Generator
    {
        $handle = $this->open($path);

        try {
            $lineNumber = 0;
            while (false !== ($cells = fgetcsv($handle, null, FicheImportTemplateGenerator::SEPARATOR, '"', ''))) {
                ++$lineNumber;
                if ($lineNumber < max(2, $fromLine)) {
                    continue;
                }
                if (!self::isDataLine($cells)) {
                    continue;
                }

                $values = array_map(static fn (mixed $cell): string => (string) $cell, $cells);
                $count = count($headers);
                $values = array_slice(array_pad($values, $count, ''), 0, $count);

                yield new RawCsvRow($lineNumber, array_combine($headers, $values));
            }
        } finally {
            fclose($handle);
        }
    }

    /** @param list<string> $headers */
    public function countDataLines(string $path, array $headers): int
    {
        $count = 0;
        foreach ($this->rows($path, $headers, 2) as $_row) {
            ++$count;
        }

        return $count;
    }

    /** @param array<int, mixed> $cells */
    private static function isDataLine(array $cells): bool
    {
        $first = ltrim(trim((string) ($cells[0] ?? '')), "\u{FEFF}");
        if (str_starts_with($first, FicheImportTemplateGenerator::HELP_PREFIX)) {
            return false;
        }

        foreach ($cells as $cell) {
            if ('' !== trim((string) $cell)) {
                return true;
            }
        }

        return false;
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
