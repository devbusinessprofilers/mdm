<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Pim\Enum\TypeFiche;

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
        private FicheDetailResolver $details,
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
        $lieu = $this->details->pour($fiche);
        if (!$lieu instanceof Lieu) {
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
        $restaurant = $this->details->pour($fiche);
        if (!$restaurant instanceof Restaurant) {
            return [];
        }

        return [
            ['path' => 'descriptionGenerale', 'label' => 'Description générale', 'text' => $restaurant->descriptionGenerale()],
        ];
    }

    /** @return list<ChampTexte> */
    private function activiteFields(Fiche $fiche): array
    {
        $activite = $this->details->pour($fiche);
        if (!$activite instanceof Activite) {
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
        $service = $this->details->pour($fiche);
        if (!$service instanceof ServiceEvenementiel) {
            return [];
        }

        return [
            ['path' => 'descriptionGenerale', 'label' => 'Description générale', 'text' => $service->descriptionGenerale()],
        ];
    }
}
