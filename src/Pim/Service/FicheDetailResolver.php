<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Pim\Enum\TypeFiche;
use App\Pim\Repository\ActiviteRepository;
use App\Pim\Repository\LieuRepository;
use App\Pim\Repository\RestaurantRepository;
use App\Pim\Repository\ServiceEvenementielRepository;

/**
 * Retrouve l'entité détail d'une fiche (Lieu, Restaurant, Activité, Service)
 * depuis sa fiche, sa gamme ou son segment d'URL : le seul endroit qui
 * connaît la correspondance gamme → repository. L'entité et sa fiche
 * partagent le même ULID.
 */
final readonly class FicheDetailResolver
{
    public function __construct(
        private LieuRepository $lieux,
        private RestaurantRepository $restaurants,
        private ActiviteRepository $activites,
        private ServiceEvenementielRepository $services,
    ) {
    }

    /** Null pour une gamme hors périmètre ou une fiche sans ligne détail. */
    public function pour(Fiche $fiche): Lieu|Restaurant|Activite|ServiceEvenementiel|null
    {
        return $this->parTypeEtId($fiche->type(), $fiche->idString());
    }

    public function parTypeEtId(TypeFiche $type, string $id): Lieu|Restaurant|Activite|ServiceEvenementiel|null
    {
        $entite = match ($type) {
            TypeFiche::Lieu => $this->lieux->find($id),
            TypeFiche::Restaurant => $this->restaurants->find($id),
            TypeFiche::Activite => $this->activites->find($id),
            TypeFiche::ServiceEvenementiel => $this->services->find($id),
            TypeFiche::Traiteur => null,
        };

        return $entite instanceof Lieu || $entite instanceof Restaurant || $entite instanceof Activite || $entite instanceof ServiceEvenementiel
            ? $entite
            : null;
    }

    /** Repository de l'entité détail d'une gamme opérationnelle. */
    public function repository(TypeFiche $type): LieuRepository|RestaurantRepository|ActiviteRepository|ServiceEvenementielRepository
    {
        return match ($type) {
            TypeFiche::Lieu => $this->lieux,
            TypeFiche::Restaurant => $this->restaurants,
            TypeFiche::Activite => $this->activites,
            TypeFiche::ServiceEvenementiel => $this->services,
            TypeFiche::Traiteur => throw new \InvalidArgumentException('Gamme hors de cette version du MDM.'),
        };
    }

    /** Depuis le segment d'URL (`lieux`, `restaurants`, `activites`, `services`). */
    public function parSlugEtId(string $slug, string $id): Lieu|Restaurant|Activite|ServiceEvenementiel|null
    {
        $type = TypeFiche::depuisSlug($slug);

        return null === $type ? null : $this->parTypeEtId($type, $id);
    }
}
