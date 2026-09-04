<?php

declare(strict_types=1);

namespace App\Pim\Api;

use App\Dam\Service\FichePhotoPresenter;
use App\Pim\Api\Dto\FicheMediaResource;
use App\Pim\Api\Dto\RestaurantListResource;
use App\Pim\Api\Dto\RestaurantResource;
use App\Pim\Entity\HorairesJours;
use App\Pim\Entity\Localisation;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\ReadModel\RestaurantListItem;

final readonly class RestaurantApiMapper
{
    public function __construct(
        private FichePhotoPresenter $photos,
        private MediaResourceFactory $medias,
    ) {
    }

    public function listItem(RestaurantListItem $item): RestaurantListResource
    {
        return new RestaurantListResource(
            $item->id,
            $item->code,
            $item->label,
            $item->ville,
            $item->status->value,
            $item->completeness,
            $item->updatedAt->format(DATE_ATOM),
        );
    }

    public function restaurant(Restaurant $restaurant): RestaurantResource
    {
        $fiche = $restaurant->fiche();
        $amplitude = HorairesJours::amplitude($restaurant->horairesJours());

        return new RestaurantResource(
            $restaurant->id(),
            $restaurant->code(),
            $restaurant->label(),
            $fiche->status()->value,
            $restaurant->completeness(),
            $restaurant->completenessByChannel(),
            $restaurant->completenessCalculatedAt()?->format(DATE_ATOM),
            $fiche->version(),
            $fiche->publishedAt()?->format(DATE_ATOM),
            $fiche->updatedAt()->format(DATE_ATOM),
            $restaurant->typesRestaurant(),
            $restaurant->typesCuisine(),
            $restaurant->specificitesAlimentaires(),
            $restaurant->typesEvenement(),
            $restaurant->siteOfficiel(),
            $restaurant->privatisationTotale(),
            $restaurant->privatisationPartielle(),
            $restaurant->joursOuverture(),
            $amplitude['ouverture'],
            $amplitude['fermeture'],
            $restaurant->horairesJours(),
            array_values(array_map(
                static fn ($period): array => [
                    'id' => $period->id(),
                    'nom' => $period->nom(),
                    'dateDebut' => $period->dateDebut()?->format('Y-m-d'),
                    'dateFin' => $period->dateFin()?->format('Y-m-d'),
                ],
                $restaurant->periodesFermeture()->toArray(),
            )),
            null === $restaurant->localisation()
                ? null
                : $this->location($restaurant->localisation()),
            array_values(array_map(
                static fn ($access): array => [
                    'id' => $access->id(),
                    'type' => $access->type()->value,
                    'nom' => $access->nom(),
                    'position' => $access->position(),
                ],
                $restaurant->acces()->toArray(),
            )),
            $restaurant->accesPmr(),
            $restaurant->toilettesPmr(),
            $restaurant->descriptionGenerale(),
            $restaurant->atouts(),
            $restaurant->capaciteAssiseMax(),
            $restaurant->capaciteEspacePrivatisable(),
            $restaurant->capaciteBanquet(),
            $restaurant->capaciteCocktail(),
            array_values(array_map(
                static fn ($room): array => [
                    'id' => $room->id(),
                    'nom' => $room->nom(),
                    'superficie' => $room->superficie(),
                    'capaciteReunion' => $room->capaciteReunion(),
                    'capaciteU' => $room->capaciteU(),
                    'capaciteClasse' => $room->capaciteClasse(),
                    'capaciteTheatre' => $room->capaciteTheatre(),
                    'capaciteCabaret' => $room->capaciteCabaret(),
                    'capaciteBanquet' => $room->capaciteBanquet(),
                    'capaciteCocktail' => $room->capaciteCocktail(),
                    'capaciteAuditorium' => $room->capaciteAuditorium(),
                    'lumiereJour' => $room->lumiereJour(),
                    'accesPmr' => $room->accesPmr(),
                    'espaceDansant' => $room->espaceDansant(),
                    'climatisee' => $room->climatisee(),
                    'position' => $room->position(),
                ],
                $restaurant->salles()->toArray(),
            )),
            $restaurant->services(),
            $restaurant->equipements(),
            $restaurant->engagementsRse(),
            $restaurant->youtubeUrl(),
            array_map(
                fn (array $photo): FicheMediaResource => $this->medias->depuisPresentation($fiche->version(), $photo),
                $this->photos->photos($fiche),
            ),
        );
    }

    /** @return array<string, mixed> */
    private function location(Localisation $location): array
    {
        $result = [];
        foreach (
            [
                'pays',
                'countryCode',
                'region',
                'departement',
                'ruePostale',
                'codePostal',
                'ville',
                'arrondissement',
                'latitude',
                'longitude',
            ] as $field
        ) {
            $result[$field] = $location->{$field}();
        }

        return $result;
    }
}
