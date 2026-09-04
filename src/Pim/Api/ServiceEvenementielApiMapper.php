<?php

declare(strict_types=1);

namespace App\Pim\Api;

use App\Dam\Service\FichePhotoPresenter;
use App\Pim\Api\Dto\FicheMediaResource;
use App\Pim\Api\Dto\ServiceEvenementielListResource;
use App\Pim\Api\Dto\ServiceEvenementielResource;
use App\Pim\Entity\Localisation;
use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Pim\ReadModel\ServiceEvenementielListItem;

final readonly class ServiceEvenementielApiMapper
{
    public function __construct(
        private FichePhotoPresenter $photos,
        private MediaResourceFactory $medias,
    ) {
    }

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
        $fixed = 'fixe' === $service->modeIntervention()?->value;
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
