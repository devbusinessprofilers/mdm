<?php

declare(strict_types=1);

namespace App\Dam\Service;

use App\Pim\Entity\Fiche;
use App\Pim\Enum\TypeFiche;
use App\Pim\Repository\ActiviteRepository;
use App\Pim\Repository\LieuRepository;
use App\Pim\Repository\RestaurantRepository;
use App\Pim\Repository\ServiceEvenementielRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class DamFicheLinkResolver
{
    public function __construct(
        private LieuRepository $lieux,
        private ActiviteRepository $activites,
        private RestaurantRepository $restaurants,
        private ServiceEvenementielRepository $services,
        private UrlGeneratorInterface $urls,
    ) {}

    public function editUrl(Fiche $fiche): ?string
    {
        [$route, $entity] = match ($fiche->type()) {
            TypeFiche::Lieu => ['app_pim_lieu_edit', $this->lieux->findOneBy(['fiche' => $fiche])],
            TypeFiche::Activite => ['app_pim_activite_edit', $this->activites->findOneByFiche($fiche)],
            TypeFiche::Restaurant => ['app_pim_restaurant_edit', $this->restaurants->findOneByFiche($fiche)],
            TypeFiche::ServiceEvenementiel => ['app_pim_service_edit', $this->services->findOneByFiche($fiche)],
            TypeFiche::Traiteur => [null, null],
        };
        if (null === $route || null === $entity) {
            return null;
        }

        return $this->urls->generate($route, ['id' => $entity->id()]);
    }
}
