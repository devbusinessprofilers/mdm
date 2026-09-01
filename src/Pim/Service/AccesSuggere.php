<?php

declare(strict_types=1);

namespace App\Pim\Service;

/**
 * Une entrée proposée pour le bloc Accès d'une fiche. Les distances sont en
 * notation canonique (point décimal) ; la mise en forme localisée appartient
 * aux consommateurs. Les champs distance/durée/mode restent null pour les
 * gammes qui ne les portent pas (Restaurant : type + nom seuls).
 */
final readonly class AccesSuggere
{
    public function __construct(
        public string $type,
        public string $nom,
        public ?string $distanceKilometres = null,
        public ?int $dureeMinutes = null,
        public ?string $modeTransport = null,
    ) {
    }
}
