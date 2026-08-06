<?php

declare(strict_types=1);

namespace App\Pim\Import\Legacy;

use App\Pim\Entity\Service\ServiceEvenementiel;

/** Résultat du mapping d'une ligne CSV production vers un ServiceEvenementiel hydraté. */
final readonly class LegacyMappedService
{
    /** @param list<string> $warnings codes de warning agrégés par la commande */
    public function __construct(
        public int $syspadId,
        public ServiceEvenementiel $service,
        public bool $publish,
        public string $gamme,
        public ?string $photosJson,
        public array $warnings,
    ) {
    }
}
