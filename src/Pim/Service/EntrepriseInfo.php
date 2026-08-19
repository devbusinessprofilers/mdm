<?php

declare(strict_types=1);

namespace App\Pim\Service;

/** Résultat d'une recherche sur l'API recherche-entreprises (annuaire des entreprises). */
final readonly class EntrepriseInfo
{
    public function __construct(
        public ?string $denomination = null,
        public ?string $raisonSociale = null,
        public ?string $siren = null,
        public ?string $siret = null,
        public ?string $numeroTva = null,
        public ?string $rue = null,
        public ?string $codePostal = null,
        public ?string $ville = null,
        public ?string $latitude = null,
        public ?string $longitude = null,
        public ?string $dirigeantPrenom = null,
        public ?string $dirigeantNom = null,
    ) {}
}
