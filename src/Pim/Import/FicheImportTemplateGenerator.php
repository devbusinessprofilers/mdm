<?php

declare(strict_types=1);

namespace App\Pim\Import;

use App\Pim\Enum\TypeFiche;
use App\Pim\Import\Schema\ColumnDefinition;
use App\Pim\Import\Schema\FicheImportSchemaRegistry;

final readonly class FicheImportTemplateGenerator
{
    public const SEPARATOR = ';';
    public const HELP_PREFIX = '#';

    public function __construct(private FicheImportSchemaRegistry $schemas)
    {
    }

    /** @return list<string> */
    public function headers(TypeFiche $type): array
    {
        $schema = $this->schemas->for($type);

        $headers = [];
        foreach ($schema->ficheColumns() as $column) {
            $headers[] = $column->header;
        }
        foreach ($schema->collections() as $collection) {
            for ($index = 1; $index <= $collection->max; ++$index) {
                foreach ($collection->columns as $column) {
                    $headers[] = $collection->header($index, $column);
                }
            }
        }

        return $headers;
    }

    /** @param resource $stream */
    public function write(TypeFiche $type, mixed $stream): void
    {
        $schema = $this->schemas->for($type);

        $headers = $this->headers($type);
        $helps = [];
        foreach ($schema->ficheColumns() as $column) {
            $helps[] = self::help($column);
        }
        foreach ($schema->collections() as $collection) {
            for ($index = 1; $index <= $collection->max; ++$index) {
                foreach ($collection->columns as $column) {
                    $helps[] = self::help($column);
                }
            }
        }

        fwrite($stream, "\u{FEFF}");
        fputcsv($stream, $headers, self::SEPARATOR, '"', '');
        // Écriture manuelle : fputcsv met des guillemets dès qu'une cellule contient un espace,
        // or le lecteur repère les lignes d'aide au # en tout début de ligne.
        fwrite($stream, implode(self::SEPARATOR, array_map(self::sanitize(...), $helps))."\n");

        fwrite($stream, "\n".self::HELP_PREFIX.'## LISTES DE VALEURS (lignes ignorées à l’import, comme toute ligne commençant par '.self::HELP_PREFIX.")\n");
        fwrite($stream, self::HELP_PREFIX."## Les photos et documents ne s’importent pas par CSV : utilisez l’administration des fiches.\n");
        foreach ($schema->lovChoices() as $attribute => $choices) {
            foreach ($choices as $code => $label) {
                fwrite($stream, implode(self::SEPARATOR, [self::HELP_PREFIX.' '.$attribute, (string) $code, self::sanitize($label)])."\n");
            }
        }
    }

    public function filename(TypeFiche $type): string
    {
        return sprintf('modele-import-%s.csv', $type->value);
    }

    private static function sanitize(string $value): string
    {
        return str_replace([self::SEPARATOR, '"', "\n", "\r"], [',', '”', ' ', ' '], $value);
    }

    private static function help(ColumnDefinition $column): string
    {
        $parts = [self::HELP_PREFIX];
        $parts[] = $column->required ? 'obligatoire' : 'optionnel';
        $parts[] = match ($column->kind->name) {
            'Int' => 'entier',
            'Bool' => '1/0 ou oui/non',
            'Decimal', 'Float' => 'décimal (point)',
            'Date' => 'date AAAA-MM-JJ',
            'Time' => 'heure HH:MM',
            'Enum', 'LovMono' => 'un code',
            'LovMulti' => 'codes séparés par |',
            'StringList' => 'valeurs séparées par |',
            'Prestataire' => 'code prestataire',
            default => 'texte'.(null !== $column->maxLength ? ' ('.$column->maxLength.' car. max)' : ''),
        };
        if ('' !== $column->help) {
            $parts[] = $column->help;
        }
        $parts[] = 'vide = inchangé, NULL = vider';

        return implode(' — ', $parts);
    }
}
