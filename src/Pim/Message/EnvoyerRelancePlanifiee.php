<?php

declare(strict_types=1);

namespace App\Pim\Message;

/** Envoi (file mail) d'une ligne du lot de relances de complétude planifié. */
final readonly class EnvoyerRelancePlanifiee
{
    public function __construct(
        public string $relancePlanifieeId,
    ) {
    }
}
