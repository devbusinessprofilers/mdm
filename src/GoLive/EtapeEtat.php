<?php

declare(strict_types=1);

namespace App\GoLive;

final readonly class EtapeEtat
{
    public function __construct(
        public EtapeStatut $statut,
        public string $detail = '',
    ) {
    }
}
