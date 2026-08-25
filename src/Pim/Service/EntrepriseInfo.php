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
        /** Libellé lisible de la catégorie juridique INSEE (null si code inconnu du référentiel). */
        public ?string $formeJuridique = null,
        /** État administratif de l'établissement : 'A' (actif), 'F'/'C' (cessé), null si inconnu. */
        public ?string $etatAdministratif = null,
        /** Vrai quand le résultat n'a été obtenu qu'en repli France entière (filtre code postal retiré). */
        public bool $rapprochementSansCodePostal = false,
    ) {}

    /** Vrai quand l'API a explicitement renvoyé un établissement cessé. */
    public function estCesse(): bool
    {
        return null !== $this->etatAdministratif && 'A' !== $this->etatAdministratif;
    }
}
