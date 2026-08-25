<?php

declare(strict_types=1);

namespace App\Ocr\Catalog;

use App\Pim\Enum\TypeFiche;
use App\Pim\Import\Schema\ColumnDefinition;
use App\Pim\Import\Schema\ColumnKind;
use App\Pim\Import\Schema\FicheImportSchemaRegistry;
use App\Pim\Lov\LovRuntimeCatalog;

final readonly class OcrFieldCatalog
{
    private const EXCLUDED_PATHS = [
        'code', 'status', 'version', 'workflow',
        // La sélection de sites de diffusion ne se lit pas dans un document.
        'sitesDiffusion',
        'administratif.modePaiementBic', 'administratif.modePaiementIban',
        'administratif.affacturageBic', 'administratif.affacturageIban',
    ];

    public function __construct(private FicheImportSchemaRegistry $schemas) {}

    /** @return list<OcrFieldDefinition> */
    public function definitions(TypeFiche $type): array
    {
        $schema = $this->schemas->for($type);
        $definitions = [];
        foreach ($schema->ficheColumns() as $column) {
            $path = $this->path($column);
            if (in_array($path, self::EXCLUDED_PATHS, true) || $this->isSensitive($path)) { continue; }
            $definitions[] = new OcrFieldDefinition($path, $this->label($column->header), $this->type($column), options: $this->options($column, $schema->lovChoices()));
        }
        foreach ($schema->collections() as $collection) {
            $columns = [];
            foreach ($collection->columns as $column) {
                $columns[] = [
                    'key' => $column->target,
                    'label' => $this->label($column->header),
                    'type' => $this->type($column),
                    'required' => $column->required,
                    'options' => $this->options($column, $schema->lovChoices()),
                ];
            }
            $definitions[] = new OcrFieldDefinition('collections.'.$collection->prefix, ucfirst(str_replace('_', ' ', $collection->prefix)), 'table', true, columns: $columns);
        }

        return $definitions;
    }

    /** @return array{version: int, fiche_type: string, fields: list<array<string, mixed>>} */
    public function snapshot(TypeFiche $type): array
    {
        return ['version' => 1, 'fiche_type' => $type->value, 'fields' => array_map(static fn (OcrFieldDefinition $field): array => $field->snapshot(), $this->definitions($type))];
    }

    /** @return list<array<string, mixed>> */
    public function boxFields(TypeFiche $type): array
    {
        return array_map(function (OcrFieldDefinition $field): array {
            $box = ['key' => $field->path, 'displayName' => $field->label, 'type' => $field->type];
            if ([] !== $field->options) {
                $box['options'] = array_map(static fn (int|string $key): array => ['key' => (string) $key], array_keys($field->options));
            }
            if ($field->collection) {
                $box['fields'] = array_map(static function (array $column): array {
                    $result = ['key' => $column['key'], 'displayName' => $column['label'], 'type' => $column['type']];
                    if ([] !== $column['options']) { $result['options'] = array_map(static fn (int|string $key): array => ['key' => (string) $key], array_keys($column['options'])); }
                    return $result;
                }, $field->columns);
            }
            return $box;
        }, $this->definitions($type));
    }

    private function path(ColumnDefinition $column): string
    {
        if ('label' === $column->target) { return 'fiche.label'; }
        return null === $column->targetPath ? $column->target : $column->targetPath.'.'.$column->target;
    }

    private function type(ColumnDefinition $column): string
    {
        return match ($column->kind) {
            ColumnKind::Int, ColumnKind::Decimal, ColumnKind::Float => 'float',
            ColumnKind::Bool => 'boolean',
            ColumnKind::Date => 'date',
            ColumnKind::LovMono, ColumnKind::Enum => 'enum',
            ColumnKind::LovMulti, ColumnKind::StringList => 'multiSelect',
            default => 'string',
        };
    }

    /**
     * @param array<string, array<string, string>> $fallback
     * @return array<string, string>
     */
    private function options(ColumnDefinition $column, array $fallback): array
    {
        if (ColumnKind::Enum === $column->kind && null !== $column->enumClass) {
            return array_combine(array_map(static fn (\BackedEnum $case): string => (string) $case->value, $column->enumClass::cases()), array_map(static fn (\BackedEnum $case): string => (string) $case->value, $column->enumClass::cases())) ?: [];
        }
        if (null === $column->lovAttribute) { return []; }
        return LovRuntimeCatalog::choices($column->lovAttribute) ?? ($fallback[$column->lovAttribute] ?? []);
    }

    private function isSensitive(string $path): bool
    {
        return 1 === preg_match('/(iban|bic|rib|urssaf|assurance|convention|conditions?Generales|signataire)/i', $path);
    }

    private function label(string $header): string
    {
        return ucfirst(str_replace('_', ' ', $header));
    }
}
