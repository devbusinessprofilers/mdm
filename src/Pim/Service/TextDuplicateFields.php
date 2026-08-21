<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Entity\Fiche;
use App\Pim\Enum\TypeFiche;
use App\Pim\Repository\ActiviteRepository;
use App\Pim\Repository\LieuRepository;
use App\Pim\Repository\RestaurantRepository;
use App\Pim\Repository\ServiceEvenementielRepository;

/**
 * Champs de texte libre long enrôlés dans la détection de doublons, par type de
 * fiche. Les libellés courts (atouts, « les plus »…) sont volontairement exclus :
 * trop brefs, ils partagent légitimement leur formulation entre fiches.
 *
 * @phpstan-type ChampTexte array{path: string, label: string, text: ?string}
 */
final readonly class TextDuplicateFields
{
    public function __construct(
        private LieuRepository $lieux,
        private RestaurantRepository $restaurants,
        private ActiviteRepository $activites,
        private ServiceEvenementielRepository $services,
    ) {
    }

    /** @return list<ChampTexte> */
    public function forFiche(Fiche $fiche): array
    {
        return match ($fiche->type()) {
            TypeFiche::Lieu => $this->lieuFields($fiche),
            TypeFiche::Restaurant => $this->restaurantFields($fiche),
            TypeFiche::Activite => $this->activiteFields($fiche),
            TypeFiche::ServiceEvenementiel => $this->serviceFields($fiche),
            TypeFiche::Traiteur => [],
        };
    }

    /** @return list<ChampTexte> */
    private function lieuFields(Fiche $fiche): array
    {
        $lieu = $this->lieux->findOneBy(['fiche' => $fiche]);
        if (null === $lieu) {
            return [];
        }

        return [
            ['path' => 'descGenerale', 'label' => 'Description générale', 'text' => $lieu->descGenerale()],
            ['path' => 'descGeneralePointInteret', 'label' => 'Points d\'intérêt à proximité', 'text' => $lieu->descGeneralePointInteret()],
            ['path' => 'chambreDescGenerale', 'label' => 'Description de l\'hébergement', 'text' => $lieu->chambreDescGenerale()],
            ['path' => 'salleReunionDescSalleSeminaire', 'label' => 'Description des salles de réunion', 'text' => $lieu->salleReunionDescSalleSeminaire()],
            ['path' => 'rseDescGenerale', 'label' => 'Engagements RSE', 'text' => $lieu->rseDescGenerale()],
        ];
    }

    /** @return list<ChampTexte> */
    private function restaurantFields(Fiche $fiche): array
    {
        $restaurant = $this->restaurants->findOneBy(['fiche' => $fiche]);
        if (null === $restaurant) {
            return [];
        }

        return [
            ['path' => 'descriptionGenerale', 'label' => 'Description générale', 'text' => $restaurant->descriptionGenerale()],
        ];
    }

    /** @return list<ChampTexte> */
    private function activiteFields(Fiche $fiche): array
    {
        $activite = $this->activites->findOneBy(['fiche' => $fiche]);
        if (null === $activite) {
            return [];
        }

        return [
            ['path' => 'descriptionGenerale', 'label' => 'Description générale', 'text' => $activite->descriptionGenerale()],
            ['path' => 'comprendPrestation', 'label' => 'Ce que comprend la prestation', 'text' => $activite->comprendPrestation()],
        ];
    }

    /** @return list<ChampTexte> */
    private function serviceFields(Fiche $fiche): array
    {
        $service = $this->services->findOneBy(['fiche' => $fiche]);
        if (null === $service) {
            return [];
        }

        return [
            ['path' => 'descriptionGenerale', 'label' => 'Description générale', 'text' => $service->descriptionGenerale()],
        ];
    }
}
