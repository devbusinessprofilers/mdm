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

    /**
     * Le fichier d'export fait foi : une cellule vide vide le champ (pour une
     * colonne présente dans le fichier), et les colonnes LOV acceptent les
     * libellés comme les codes.
     */
    public function convert(FicheImportSchemaInterface $schema, RawCsvRow $row): ConvertedRow
    {
        $errors = [];
        $fields = [];
        $code = null;
        $lovChoices = $schema->lovChoices();

        foreach ($schema->ficheColumns() as $column) {
            $raw = $row->cell($column->header);
            if ('' === $raw) {
                // La cellule vide vaut consigne de vidage — mais seulement
                // pour une colonne réellement présente dans le fichier,
                // vidable, et hors identifiants (code, label).
                if ($column->nullable
                    && !in_array($column->header, ['code', 'label'], true)
                    && array_key_exists($column->header, $row->cells)
                ) {
                    $fields[] = new ConvertedValue($column, self::emptyValueFor($column), clear: true);
                }
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
            } elseif ([] !== $collection->columns
                && array_key_exists($collection->header(1, $collection->columns[0]), $row->cells)
            ) {
                // La collection est dans le fichier mais tous ses groupes
                // sont vides — elle se remplace par rien.
                $collections[$collection->prefix] = [];
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
                if (null === $column->lovAttribute) {
                    return strtoupper($raw);
                }
                $code = self::resoudreCodeLov($raw, $lovChoices[$column->lovAttribute] ?? []);
                if (null === $code) {
                    $error = self::messageLovInconnu($column->lovAttribute, $raw, $lovChoices[$column->lovAttribute] ?? []);

                    return null;
                }

                return $code;
            case ColumnKind::LovMulti:
                $codes = [];
                foreach (self::splitList($raw, false) as $brut) {
                    if (null === $column->lovAttribute) {
                        $codes[] = strtoupper($brut);
                        continue;
                    }
                    $code = self::resoudreCodeLov($brut, $lovChoices[$column->lovAttribute] ?? []);
                    if (null === $code) {
                        $error = self::messageLovInconnu($column->lovAttribute, $brut, $lovChoices[$column->lovAttribute] ?? []);

                        return null;
                    }
                    $codes[] = $code;
                }

                return $codes;
            case ColumnKind::StringList:
                return self::splitList($raw, false);
            case ColumnKind::Prestataire:
                // La casse d'un libellé compte : le processeur résout code
                // puis libellé.
                return trim($raw);
            case ColumnKind::SitesDiffusion:
                // Libellés résolus sur le référentiel par le processeur ; le
                // retour-ligne est accepté en plus du | (colonne « Attribution
                // visibilité » du XLSX production : un site par ligne).
                return array_values(array_filter(
                    array_map(static fn (string $value): string => trim($value), preg_split('/[|\r\n]+/', $raw) ?: []),
                    static fn (string $value): bool => '' !== $value,
                ));
        }
    }

    /**
     * Message d'erreur LOV : valeur lue + suggestion du candidat le plus
     * proche parmi ce que resoudreCodeLov aurait accepté — libellés d'abord,
     * le format d'export les écrit.
     *
     * @param array<string, string> $choices code => libellé
     */
    private static function messageLovInconnu(string $attribut, string $brut, array $choices): string
    {
        $message = sprintf('Code LOV inconnu pour %s : « %s ».', $attribut, trim($brut));
        $candidats = [...array_values($choices), ...array_map(strval(...), array_keys($choices))];
        $suggestion = SuggestionProche::trouver($brut, $candidats);
        if (null !== $suggestion) {
            $message .= sprintf(' Vouliez-vous dire « %s » ?', $suggestion);
        }

        return $message;
    }

    /**
     * Code LOV depuis un code ou un libellé (insensible à la casse) — le
     * format d'export écrit les libellés.
     *
     * @param array<string, string> $choices code => libellé
     */
    private static function resoudreCodeLov(string $raw, array $choices): ?string
    {
        $code = strtoupper(trim($raw));
        if (isset($choices[$code])) {
            return $code;
        }
        $cherche = mb_strtolower(trim($raw));
        foreach ($choices as $candidat => $libelle) {
            if (mb_strtolower($libelle) === $cherche) {
                return (string) $candidat;
            }
        }

        return null;
    }

    private static function emptyValueFor(ColumnDefinition $column): mixed
    {
        return match ($column->kind) {
            ColumnKind::LovMulti, ColumnKind::StringList, ColumnKind::SitesDiffusion => [],
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
