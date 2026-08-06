<?php

declare(strict_types=1);

namespace App\Pim\Import\Legacy;

use App\Pim\Entity\Restaurant\Restaurant;

/** Résultat du mapping d'une ligne CSV production vers un Restaurant hydraté. */
final readonly class LegacyMappedRestaurant
{
    /** @param list<string> $warnings codes de warning agrégés par la commande */
    public function __construct(
        public int $syspadId,
        public Restaurant $restaurant,
        public bool $publish,
        public string $gamme,
        public ?string $photosJson,
        public array $warnings,
    ) {
    }
}
