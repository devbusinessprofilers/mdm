<?php

declare(strict_types=1);

namespace App\Pim\Message;

/** Génération en tâche de fond d'un export Excel du référentiel. */
final readonly class GenererReferentielExport
{
    public function __construct(public string $exportId)
    {
    }
}
