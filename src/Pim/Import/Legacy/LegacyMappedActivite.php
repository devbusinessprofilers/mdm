<?php

declare(strict_types=1);

namespace App\Pim\Import\Legacy;

use App\Pim\Entity\Activite\Activite;

/** Résultat du mapping d'une ligne CSV production vers une Activite hydratée. */
final readonly class LegacyMappedActivite
{
    /** @param list<string> $warnings codes de warning agrégés par la commande */
    public function __construct(
        public int $syspadId,
        public Activite $activite,
        public bool $publish,
        public string $gamme,
        public ?string $photosJson,
        public array $warnings,
    ) {
    }
}
