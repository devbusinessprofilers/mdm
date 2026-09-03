<?php

declare(strict_types=1);

namespace App\Pim\Service\DataTourisme;

/**
 * Point d'intérêt DATAtourisme normalisé depuis le flux JSON-LD (open data des
 * offices de tourisme). Seuls les champs exploitables par l'enrichissement sont
 * retenus : nom, localisation, description et libellés d'équipements.
 */
final readonly class DataTourismePoi
{
    /** @param list<string> $features libellés d'équipements/services (français, minuscules) */
    public function __construct(
        public string $nom,
        public ?string $codePostal = null,
        public ?string $ville = null,
        public ?string $latitude = null,
        public ?string $longitude = null,
        public ?string $description = null,
        public array $features = [],
    ) {
    }
}
