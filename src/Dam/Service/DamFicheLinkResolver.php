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
    ) {
    }

    public function editUrl(Fiche $fiche): ?string
    {
        // L'éditeur MDM est la vue unique d'une fiche.
        [$gamme, $entity] = match ($fiche->type()) {
            TypeFiche::Lieu => [null, $this->lieux->findOneBy(['fiche' => $fiche])],
            TypeFiche::Activite => ['activites', $this->activites->findOneByFiche($fiche)],
            TypeFiche::Restaurant => ['restaurants', $this->restaurants->findOneByFiche($fiche)],
            TypeFiche::ServiceEvenementiel => ['services', $this->services->findOneByFiche($fiche)],
            TypeFiche::Traiteur => ['', null],
        };
        if (null === $entity) {
            return null;
        }

        return null === $gamme
            ? $this->urls->generate('app_mdm_fiche_lieu', ['id' => $entity->id()])
            : $this->urls->generate('app_mdm_fiche_gamme', ['gamme' => $gamme, 'id' => $entity->id()]);
    }
}
