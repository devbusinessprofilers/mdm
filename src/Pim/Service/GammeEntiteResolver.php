<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Pim\Repository\ActiviteRepository;
use App\Pim\Repository\RestaurantRepository;
use App\Pim\Repository\ServiceEvenementielRepository;

/**
 * Résout l'entité d'une fiche de gamme depuis le segment d'URL
 * (restaurants|activites|services) et son identifiant.
 */
final readonly class GammeEntiteResolver
{
    public function __construct(
        private RestaurantRepository $restaurants,
        private ActiviteRepository $activites,
        private ServiceEvenementielRepository $services,
    ) {
    }

    public function resolve(string $gamme, string $id): Restaurant|Activite|ServiceEvenementiel|null
    {
        $entite = match ($gamme) {
            'restaurants' => $this->restaurants->find($id),
            'activites' => $this->activites->find($id),
            default => $this->services->find($id),
        };

        return $entite instanceof Restaurant || $entite instanceof Activite || $entite instanceof ServiceEvenementiel
            ? $entite
            : null;
    }
}
