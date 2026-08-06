<?php

declare(strict_types=1);

namespace App\Pim\Import\Legacy;

use App\Pim\Entity\Lieu\Lieu;

/** Résultat du mapping d'une ligne CSV production vers un Lieu hydraté. */
final readonly class LegacyMappedLieu
{
    /** @param list<string> $warnings codes de warning agrégés par la commande */
    public function __construct(
        public int $syspadId,
        public Lieu $lieu,
        public bool $publish,
        public string $gamme,
        public ?string $photosJson,
        public array $warnings,
    ) {
    }
}
