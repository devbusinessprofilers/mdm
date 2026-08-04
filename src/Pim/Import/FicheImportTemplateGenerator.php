<?php

declare(strict_types=1);

namespace App\Pim\Import;

use App\Pim\Enum\TypeFiche;
use App\Pim\Import\Schema\ColumnDefinition;
use App\Pim\Import\Schema\FicheImportSchemaRegistry;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

final readonly class FicheImportTemplateGenerator
{
    public const SEPARATOR = ';';
    public const HELP_PREFIX = '#';
    public const DATA_SHEET = 'Données';
    public const NOTICE_SHEET = 'Notice & LOV';

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

    /**
     * Écrit le modèle XLSX : feuille « Données » réservée aux colonnes de l'entité,
     * feuille « Notice & LOV » pour l'aide et les listes de valeurs.
     */
    public function write(TypeFiche $type, string $path): void
    {
        $schema = $this->schemas->for($type);

        $writer = new Writer();
        $writer->openToFile($path);

        try {
            $writer->getCurrentSheet()->setName(self::DATA_SHEET);
            $writer->addRow(Row::fromValues($this->headers($type)));

            $writer->addNewSheetAndMakeItCurrent()->setName(self::NOTICE_SHEET);
            $writer->addRow(Row::fromValues(['Colonne', 'Type attendu', 'Obligation', 'Aide']));
            foreach ($schema->ficheColumns() as $column) {
                $writer->addRow(Row::fromValues(self::noticeRow($column->header, $column)));
            }
            foreach ($schema->collections() as $collection) {
                foreach ($collection->columns as $column) {
                    $label = sprintf('%s_N_%s (N = 1 à %d)', $collection->prefix, $column->header, $collection->max);
                    $writer->addRow(Row::fromValues(self::noticeRow($label, $column)));
                }
            }

            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValues(['Une cellule vide laisse le champ inchangé ; la valeur NULL vide le champ.']));
            $writer->addRow(Row::fromValues(['Les photos et documents ne s’importent pas par fichier : utilisez l’administration des fiches.']));

            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValues(['Attribut', 'Code', 'Libellé']));
            foreach ($schema->lovChoices() as $attribute => $choices) {
                foreach ($choices as $code => $label) {
                    $writer->addRow(Row::fromValues([$attribute, (string) $code, $label]));
                }
            }
        } finally {
            $writer->close();
        }
    }

    public function filename(TypeFiche $type): string
    {
        return sprintf('modele-import-%s.xlsx', $type->value);
    }

    /** @return list<string> */
    private static function noticeRow(string $label, ColumnDefinition $column): array
    {
        $kind = match ($column->kind->name) {
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

        return [$label, $kind, $column->required ? 'obligatoire' : 'optionnel', $column->help];
    }
}
