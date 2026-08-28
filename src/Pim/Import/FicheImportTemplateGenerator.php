<?php

declare(strict_types=1);

namespace App\Pim\Import;

use App\Pim\Enum\TypeFiche;
use App\Pim\Import\Schema\FicheImportSchemaRegistry;

/**
 * Catalogue des en-têtes reconnus par l'import de fiches : la validation au
 * démarrage d'un job s'y réfère (l'écran de téléchargement de modèle XLSX a
 * disparu avec l'import par modèle, remplacé par l'import en masse).
 */
final readonly class FicheImportTemplateGenerator
{
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
}
