<?php

declare(strict_types=1);

namespace App\Pim\Import\Legacy;

/**
 * Lecteur streaming d'un dump mysqldump : extrait les lignes des tables
 * demandées sans base intermédiaire (utilisable en production au déploiement).
 * Hypothèses vérifiées sur le dump : `INSERT INTO `t` VALUES` sans liste de
 * colonnes (ordre du CREATE TABLE), un tuple par ligne physique (mysqldump
 * échappe les retours à la ligne en \n), fin d'INSERT sur `;`.
 */
final class LegacySqlDumpReader
{
    /**
     * @param list<string> $tables
     *
     * @return \Generator<array{0: string, 1: array<string, ?string>}> [table, ligne associative]
     */
    public function rows(string $path, array $tables): \Generator
    {
        $handle = @fopen($path, 'rb');
        if (false === $handle) {
            throw new \RuntimeException(sprintf('Dump SQL illisible : %s', $path));
        }
        $wanted = array_fill_keys($tables, true);
        /** @var array<string, list<string>> $columns */
        $columns = [];
        try {
            $insertTable = null;
            while (false !== ($line = fgets($handle))) {
                if (null !== $insertTable) {
                    $trimmed = rtrim($line);
                    $isLast = str_ends_with($trimmed, ';');
                    if (str_starts_with($trimmed, '(')) {
                        $values = $this->parseTuple($trimmed);
                        $names = $columns[$insertTable];
                        if (count($values) !== count($names)) {
                            throw new \RuntimeException(sprintf('Tuple inattendu pour %s : %d valeurs, %d colonnes.', $insertTable, count($values), count($names)));
                        }
                        yield [$insertTable, array_combine($names, $values)];
                    }
                    if ($isLast) {
                        $insertTable = null;
                    }
                    continue;
                }
                if (1 === preg_match('/^CREATE TABLE `([^`]+)` \(/', $line, $matches) && isset($wanted[$matches[1]])) {
                    $columns[$matches[1]] = $this->readColumns($handle);
                    continue;
                }
                if (1 === preg_match('/^INSERT INTO `([^`]+)` VALUES/', $line, $matches) && isset($wanted[$matches[1]])) {
                    if (!isset($columns[$matches[1]])) {
                        throw new \RuntimeException(sprintf('INSERT rencontré avant le CREATE TABLE de %s.', $matches[1]));
                    }
                    $insertTable = $matches[1];
                }
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param resource $handle
     *
     * @return list<string>
     */
    private function readColumns($handle): array
    {
        $columns = [];
        while (false !== ($line = fgets($handle))) {
            $line = ltrim($line);
            if (1 === preg_match('/^`([^`]+)`/', $line, $matches)) {
                $columns[] = $matches[1];
                continue;
            }
            // PRIMARY KEY / KEY / UNIQUE / CONSTRAINT / fin de définition.
            if (str_starts_with($line, ')')) {
                break;
            }
        }
        if ([] === $columns) {
            throw new \RuntimeException('Définition de table sans colonnes dans le dump.');
        }

        return $columns;
    }

    /**
     * Décode un tuple `(v1,'texte échappé',NULL,…)` en liste de valeurs
     * (NULL SQL → null PHP, échappements mysqldump décodés).
     *
     * @return list<?string>
     */
    private function parseTuple(string $line): array
    {
        $values = [];
        $length = strlen($line);
        $i = 1; // saute la parenthèse ouvrante
        while ($i < $length) {
            $char = $line[$i];
            if (',' === $char || ' ' === $char) {
                ++$i;
                continue;
            }
            if (')' === $char) {
                break;
            }
            if ("'" === $char) {
                $buffer = '';
                ++$i;
                while ($i < $length) {
                    $char = $line[$i];
                    if ('\\' === $char) {
                        $next = $line[$i + 1] ?? '';
                        $buffer .= match ($next) {
                            'n' => "\n", 'r' => "\r", 't' => "\t", '0' => "\0",
                            'Z' => "\x1a", 'b' => "\x08",
                            default => $next,
                        };
                        $i += 2;
                        continue;
                    }
                    if ("'" === $char) {
                        // '' = quote échappée en SQL standard.
                        if ("'" === ($line[$i + 1] ?? '')) {
                            $buffer .= "'";
                            $i += 2;
                            continue;
                        }
                        ++$i;
                        break;
                    }
                    $buffer .= $char;
                    ++$i;
                }
                $values[] = $buffer;
                continue;
            }
            // Valeur non quotée : nombre, NULL…
            $start = $i;
            while ($i < $length && ',' !== $line[$i] && ')' !== $line[$i]) {
                ++$i;
            }
            $raw = trim(substr($line, $start, $i - $start));
            $values[] = 'NULL' === strtoupper($raw) ? null : $raw;
        }

        return $values;
    }
}
