<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Entity\Fiche;

final readonly class FicheCreationResult
{
    public function __construct(
        public Fiche $fiche,
        public ?EntrepriseInfo $entreprise,
    ) {}
}
