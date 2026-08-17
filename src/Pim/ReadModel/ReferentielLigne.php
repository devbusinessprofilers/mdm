<?php

declare(strict_types=1);

namespace App\Pim\ReadModel;

use App\Pim\Enum\StatutFiche;
use App\Pim\Enum\TypeFiche;

/** Ligne du tableau de la liste du référentiel. */
final readonly class ReferentielLigne
{
    public function __construct(
        public string $id,
        public TypeFiche $type,
        public int $code,
        public ?string $label,
        public ?string $ville,
        public StatutFiche $status,
        public ?int $completeness,
        public int $canaux,
        public \DateTimeImmutable $updatedAt,
        public ?string $auteur,
        public ?string $contributeur,
        public ?string $vignette,
        public bool $marqueIa,
        public ?string $typologie,
        public ?string $pays,
        public bool $actif,
        public bool $premium,
    ) {
    }
}
