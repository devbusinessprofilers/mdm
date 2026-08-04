<?php

declare(strict_types=1);

namespace App\Pim\Import\Schema;

use App\Pim\Entity\Fiche;
use App\Pim\Enum\TypeFiche;

interface FicheImportSchemaInterface
{
    public function type(): TypeFiche;

    /** @return list<ColumnDefinition> tronc commun (code, label), localisation puis champs du type */
    public function ficheColumns(): array;

    /** @return list<CollectionSchema> */
    public function collections(): array;

    /** @return array<string, array<string, string>> code attribut => [code valeur => libellé] */
    public function lovChoices(): array;

    public function createAggregate(): object;

    public function findAggregateByFiche(Fiche $fiche): ?object;

    public function ficheOf(object $aggregate): Fiche;
}
