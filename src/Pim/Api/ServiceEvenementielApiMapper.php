<?php

declare(strict_types=1);

namespace App\Pim\Api;

use App\Dam\Service\FichePhotoPresenter;
use App\Pim\Api\Dto\LieuMediaResource;
use App\Pim\Api\Dto\ServiceEvenementielListResource;
use App\Pim\Api\Dto\ServiceEvenementielResource;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Entity\Localisation;
use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Pim\ReadModel\ServiceEvenementielListItem;
use App\Shared\Service\ParametreProviderInterface;

final readonly class ServiceEvenementielApiMapper
{
    public function __construct(
        private FichePhotoPresenter $photos,
        private ParametreProviderInterface $parametres,
    ) {}

    public function listItem(
        ServiceEvenementielListItem $item,
    ): ServiceEvenementielListResource {
        return new ServiceEvenementielListResource(
            $item->id,
            $item->code,
            $item->label,
            $item->ville,
            $item->status->value,
            $item->completeness,
            $item->updatedAt->format(DATE_ATOM),
        );
    }

    public function service(
        ServiceEvenementiel $service,
    ): ServiceEvenementielResource {
        $fiche = $service->fiche();
        $fixed = "fixe" === $service->modeIntervention()?->value;
        $location = $fixed ? $service->localisation() : null;

        return new ServiceEvenementielResource(
            $service->id(),
            $service->code(),
            $service->label(),
            $fiche->status()->value,
            $service->completeness(),
            $service->completenessByChannel(),
            $service->completenessCalculatedAt()?->format(DATE_ATOM),
            $fiche->version(),
            $fiche->publishedAt()?->format(DATE_ATOM),
            $fiche->updatedAt()->format(DATE_ATOM),
            $service->prestations(),
            $service->sousPrestations(),
            $service->prestataireEsat(),
            $service->demarcheRse(),
            $service->descriptionGenerale(),
            $service->adapteFemmesEnceintes(),
            $service->adapteMalentendants(),
            $service->adapteMalvoyants(),
            $service->materielInclus(),
            $service->equipementParticipantsRequis(),
            $service->equipementReceptionRequis(),
            $service->contraintesLogistiques(),
            $service->participantsMin(),
            $service->participantsMax(),
            $service->dureeMinutes(),
            $service->modeIntervention()?->value,
            $fixed ? [] : $service->paysMobiles(),
            $fixed ? [] : $service->regionsMobiles(),
            $fixed ? [] : $service->departementsMobiles(),
            null === $location ? null : $this->location($location),
            $service->tarifParPrestation(),
            $service->tarifParPersonne(),
            $service->tarifParJour(),
            $service->tarifParDemiJournee(),
            $service->tarifParHeure(),
            $service->surDevis(),
            $service->youtubeUrl(),
            array_map(
                fn(array $photo): LieuMediaResource => $this->photo(
                    $service,
                    $photo,
                ),
                $this->photos->photos($fiche),
            ),
            array_values(array_map(
                static fn ($access): array => [
                    'id' => $access->id(),
                    'type' => $access->type()->value,
                    'nom' => $access->nom(),
                    'distanceKilometres' => $access->distanceKilometres(),
                    'dureeMinutes' => $access->dureeMinutes(),
                    'modeTransport' => $access->modeTransport(),
                    'position' => $access->position(),
                ],
                $service->acces()->toArray(),
            )),
            $service->accesPmr(),
            $service->materielAdaptePmr(),
        );
    }

    public function media(
        ServiceEvenementiel $service,
        RessourceLieu $resource,
    ): LieuMediaResource {
        foreach ($this->photos->photos($service->fiche()) as $photo) {
            if ($photo["resource"] === $resource) {
                return $this->photo($service, $photo);
            }
        }

        return new LieuMediaResource(
            $resource->id(),
            $service->fiche()->version(),
            $resource->damAssetId(),
            $resource->usage(),
            $resource->legende(),
            $resource->position(),
            $resource->rightsGranted(),
            $resource->source(),
            $resource->crop(),
            $resource->rotation(),
            null,
            null,
            [],
            $resource->keywords(),
            $resource->rightsExpiresAt()?->format('Y-m-d'),
            $resource->rightsValidity(alerteJours: $this->parametres->int('dam.delai_alerte_droits_jours'))->value,
        );
    }

    /** @param array{resource:RessourceLieu,asset:\App\Dam\Entity\MediaAsset|null,url:string|null,thumbnail_url:string|null,variants:list<array{name:string,width:int,height:int,url:string|null}>} $photo */
    private function photo(
        ServiceEvenementiel $service,
        array $photo,
    ): LieuMediaResource {
        $resource = $photo["resource"];
        return new LieuMediaResource(
            $resource->id(),
            $service->fiche()->version(),
            $resource->damAssetId(),
            $resource->usage(),
            $resource->legende(),
            $resource->position(),
            $resource->rightsGranted(),
            $resource->source(),
            $resource->crop(),
            $resource->rotation(),
            $photo["asset"]?->status()->value,
            $photo["url"],
            $photo["variants"],
            $resource->keywords(),
            $resource->rightsExpiresAt()?->format('Y-m-d'),
            $resource->rightsValidity(alerteJours: $this->parametres->int('dam.delai_alerte_droits_jours'))->value,
        );
    }

    /** @return array<string, mixed> */
    private function location(Localisation $location): array
    {
        $result = [];
        foreach (
            [
                "pays",
                "countryCode",
                "region",
                "departement",
                "ruePostale",
                "codePostal",
                "ville",
                "arrondissement",
                "latitude",
                "longitude",
            ]
            as $field
        ) {
            $result[$field] = $location->{$field}();
        }
        return $result;
    }
}
