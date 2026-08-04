<?php

declare(strict_types=1);

namespace App\Pim\Import;

use App\Pim\Import\Dto\ConvertedRow;
use App\Pim\Import\Dto\ConvertedValue;
use App\Pim\Import\Dto\RawCsvRow;
use App\Pim\Import\Dto\RowError;
use App\Pim\Import\Schema\ColumnDefinition;
use App\Pim\Import\Schema\ColumnKind;
use App\Pim\Import\Schema\FicheImportSchemaInterface;

final class RowConverter
{
    public const NULL_SENTINEL = 'NULL';

    private const TRUE_VALUES = ['1', 'oui', 'true', 'vrai'];
    private const FALSE_VALUES = ['0', 'non', 'false', 'faux'];

    public function convert(FicheImportSchemaInterface $schema, RawCsvRow $row): ConvertedRow
    {
        $errors = [];
        $fields = [];
        $code = null;
        $lovChoices = $schema->lovChoices();

        foreach ($schema->ficheColumns() as $column) {
            $raw = $row->cell($column->header);
            if ('' === $raw) {
                continue;
            }

            if ('code' === $column->header) {
                $parsed = filter_var($raw, FILTER_VALIDATE_INT);
                if (false === $parsed || $parsed < 1) {
                    $errors[] = new RowError($row->lineNumber, $column->header, 'Code de fiche invalide : entier positif attendu.');
                } else {
                    $code = $parsed;
                }
                continue;
            }

            if (0 === strcasecmp($raw, self::NULL_SENTINEL)) {
                if (!$column->nullable) {
                    $errors[] = new RowError($row->lineNumber, $column->header, 'Cette colonne ne peut pas être vidée.');
                } else {
                    $fields[] = new ConvertedValue($column, self::emptyValueFor($column), clear: true);
                }
                continue;
            }

            $error = null;
            $value = $this->parse($column, $raw, $lovChoices, $error);
            if (null !== $error) {
                $errors[] = new RowError($row->lineNumber, $column->header, $error);
                continue;
            }
            $fields[] = new ConvertedValue($column, $value);
        }

        $collections = [];
        foreach ($schema->collections() as $collection) {
            $entries = [];
            $touched = false;
            for ($index = 1; $index <= $collection->max; ++$index) {
                $entry = [];
                $filled = false;
                foreach ($collection->columns as $column) {
                    $raw = $row->cell($collection->header($index, $column));
                    if ('' === $raw || 0 === strcasecmp($raw, self::NULL_SENTINEL)) {
                        continue;
                    }
                    $filled = true;
                    $error = null;
                    $value = $this->parse($column, $raw, $lovChoices, $error);
                    if (null !== $error) {
                        $errors[] = new RowError($row->lineNumber, $collection->header($index, $column), $error);
                        continue;
                    }
                    $entry[] = new ConvertedValue($column, $value);
                }
                if (!$filled) {
                    continue;
                }
                $touched = true;
                foreach ($collection->columns as $column) {
                    if ($column->required && '' === $row->cell($collection->header($index, $column))) {
                        $errors[] = new RowError($row->lineNumber, $collection->header($index, $column), 'Champ obligatoire pour ce groupe.');
                    }
                }
                $entries[] = $entry;
            }
            if ($touched) {
                $collections[$collection->prefix] = $entries;
            }
        }

        return new ConvertedRow($row->lineNumber, $code, $fields, $collections, $errors);
    }

    /** @param array<string, array<string, string>> $lovChoices */
    private function parse(ColumnDefinition $column, string $raw, array $lovChoices, ?string &$error): mixed
    {
        switch ($column->kind) {
            case ColumnKind::Text:
                if (null !== $column->maxLength && mb_strlen($raw) > $column->maxLength) {
                    $error = sprintf('Texte trop long (%d caractères max).', $column->maxLength);

                    return null;
                }

                return $raw;
            case ColumnKind::Int:
                $value = filter_var($raw, FILTER_VALIDATE_INT);
                if (false === $value) {
                    $error = 'Entier attendu.';

                    return null;
                }

                return $value;
            case ColumnKind::Bool:
                $lower = mb_strtolower($raw);
                if (in_array($lower, self::TRUE_VALUES, true)) {
                    return true;
                }
                if (in_array($lower, self::FALSE_VALUES, true)) {
                    return false;
                }
                $error = 'Booléen attendu (1/0, oui/non).';

                return null;
            case ColumnKind::Decimal:
                if (1 !== preg_match('/^-?\d+(\.\d+)?$/', $raw)) {
                    $error = 'Décimal attendu, point comme séparateur.';

                    return null;
                }

                return $raw;
            case ColumnKind::Float:
                if (1 !== preg_match('/^-?\d+(\.\d+)?$/', $raw)) {
                    $error = 'Décimal attendu, point comme séparateur.';

                    return null;
                }

                return (float) $raw;
            case ColumnKind::Date:
                $value = \DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
                if (false === $value) {
                    $error = 'Date attendue au format AAAA-MM-JJ.';

                    return null;
                }

                return $value;
            case ColumnKind::Time:
                $value = \DateTimeImmutable::createFromFormat('!H:i', $raw);
                if (false === $value) {
                    $error = 'Heure attendue au format HH:MM.';

                    return null;
                }

                return $value;
            case ColumnKind::Enum:
                $enumClass = $column->enumClass;
                if (null === $enumClass) {
                    throw new \LogicException(sprintf('Colonne %s sans classe d’enum.', $column->header));
                }
                $value = $enumClass::tryFrom($raw);
                if (null === $value) {
                    $error = sprintf('Valeur inconnue, attendu : %s.', implode('|', array_map(static fn (\BackedEnum $case): string => (string) $case->value, $enumClass::cases())));

                    return null;
                }

                return $value;
            case ColumnKind::LovMono:
                $code = strtoupper($raw);
                if (null !== $column->lovAttribute && !isset($lovChoices[$column->lovAttribute][$code])) {
                    $error = sprintf('Code LOV inconnu pour %s.', $column->lovAttribute);

                    return null;
                }

                return $code;
            case ColumnKind::LovMulti:
                $codes = self::splitList($raw, true);
                foreach ($codes as $code) {
                    if (null !== $column->lovAttribute && !isset($lovChoices[$column->lovAttribute][$code])) {
                        $error = sprintf('Code LOV inconnu pour %s : %s.', $column->lovAttribute, $code);

                        return null;
                    }
                }

                return $codes;
            case ColumnKind::StringList:
                return self::splitList($raw, false);
            case ColumnKind::Prestataire:
                return strtoupper($raw);
        }
    }

    private static function emptyValueFor(ColumnDefinition $column): mixed
    {
        return match ($column->kind) {
            ColumnKind::LovMulti, ColumnKind::StringList => [],
            default => null,
        };
    }

    /** @return list<string> */
    private static function splitList(string $raw, bool $uppercase): array
    {
        $values = array_values(array_filter(
            array_map(static fn (string $value): string => trim($value), explode('|', $raw)),
            static fn (string $value): bool => '' !== $value,
        ));

        return $uppercase ? array_map(static fn (string $value): string => strtoupper($value), $values) : $values;
    }
}
