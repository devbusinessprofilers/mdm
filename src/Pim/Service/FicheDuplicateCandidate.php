<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Enum\StatutFiche;
use App\Pim\Enum\TypeFiche;

/** Fiche existante potentiellement identique à celle en cours de création. */
final readonly class FicheDuplicateCandidate
{
    /** @param list<string> $reasons parmi 'nom', 'adresse', 'siret' */
    public function __construct(
        public string $ficheId,
        public TypeFiche $type,
        public int $code,
        public ?string $label,
        public ?string $ville,
        public StatutFiche $status,
        public array $reasons,
        public ?string $url,
    ) {}
}
