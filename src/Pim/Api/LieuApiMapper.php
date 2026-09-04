<?php

declare(strict_types=1);

namespace App\Pim\Api;

use App\Dam\Service\LieuPhotoPresenter;
use App\Pim\Api\Dto\FicheMediaResource;
use App\Pim\Api\Dto\LieuListResource;
use App\Pim\Api\Dto\LieuResource;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Form\LieuFormCatalog;
use App\Pim\ReadModel\LieuListItem;

final readonly class LieuApiMapper
{
    public function __construct(
        private LieuPhotoPresenter $photos,
        private MediaResourceFactory $medias,
    ) {
    }

    public function listItem(LieuListItem $item): LieuListResource
    {
        return new LieuListResource(
            $item->id,
            $item->code,
            $item->label,
            $item->ville,
            $item->status->value,
            $item->completeness,
            $item->updatedAt->format(DATE_ATOM),
        );
    }

    public function lieu(Lieu $lieu): LieuResource
    {
        $fiche = $lieu->fiche();

        return new LieuResource(
            id: $lieu->id(),
            code: $lieu->code(),
            label: $lieu->label(),
            status: $fiche->status()->value,
            completeness: $lieu->completeness(),
            completenessByChannel: $lieu->completenessByChannel(),
            completenessCalculatedAt: $lieu->completenessCalculatedAt()?->format(DATE_ATOM),
            version: $fiche->version(),
            publishedAt: $fiche->publishedAt()?->format(DATE_ATOM),
            updatedAt: $fiche->updatedAt()->format(DATE_ATOM),
            generaleTypologie: $lieu->generaleTypologie(),
            generaleWebsiteUrl: $lieu->generaleWebsiteUrl(),
            informationsGenerales: $this->fields(
                $lieu,
                LieuFormCatalog::general(),
            ),
            disponibilites: $this->fields(
                $lieu,
                LieuFormCatalog::availability(),
            ),
            accessibiliteDescription: $this->fields(
                $lieu,
                LieuFormCatalog::accessibilityAndDescription(),
            ),
            hebergement: $this->fields($lieu, LieuFormCatalog::accommodation()),
            syntheseSalles: $this->fields(
                $lieu,
                LieuFormCatalog::meetingRooms(),
            ),
            equipementsServices: $this->fields(
                $lieu,
                LieuFormCatalog::equipmentAndServices(),
            ),
            rse: $this->fields($lieu, LieuFormCatalog::rse()),
            loisirs: $this->fields($lieu, LieuFormCatalog::leisure()),
            restauration: $this->fields($lieu, LieuFormCatalog::restaurant()),
            visibilite: $this->fields($lieu, LieuFormCatalog::visibility()),
            localisation: null === $lieu->localisation()
                ? null
                : $this->namedFields($lieu->localisation(), [
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
                ]),
            administratif: $this->fields(
                $lieu->administratif(),
                LieuFormCatalog::administrative(),
            ),
            tarification: $this->fields(
                $lieu->tarification(),
                LieuFormCatalog::pricing(),
            ),
            salles: array_values(
                array_map(
                    fn (object $salle): array => $this->namedFields($salle, [
                        'id',
                        'nom',
                        'superficie',
                        'capaciteReunion',
                        'capaciteU',
                        'capaciteClasse',
                        'capaciteTheatre',
                        'capaciteCabaret',
                        'capaciteBanquet',
                        'capaciteCocktail',
                        'capaciteAuditorium',
                        'lumiereJour',
                        'accesPmr',
                        'espaceDansant',
                        'climatisee',
                        'position',
                    ]),
                    $lieu->salles()->toArray(),
                ),
            ),
            periodesFermeture: array_values(
                array_map(
                    fn (object $periode): array => $this->namedFields($periode, [
                        'id',
                        'nom',
                        'dateDebut',
                        'dateFin',
                    ]),
                    $lieu->periodesFermeture()->toArray(),
                ),
            ),
            acces: array_values(
                array_map(
                    fn (object $acces): array => $this->namedFields($acces, [
                        'id',
                        'type',
                        'nom',
                        'distanceKilometres',
                        'dureeMinutes',
                        'modeTransport',
                        'position',
                    ]),
                    $lieu->acces()->toArray(),
                ),
            ),
            medias: array_map(
                fn (array $photo): FicheMediaResource => $this->medias->depuisPresentation($lieu->fiche()->version(), $photo),
                $this->photos->photos($lieu),
            ),
        );
    }

    /**
     * @param array<string, array<string, mixed>> $definitions
     *
     * @return array<string, mixed>
     */
    private function fields(object $source, array $definitions): array
    {
        // Les rappels en lecture seule de l'éditeur (commission) n'ont pas d'accesseur.
        $definitions = array_filter($definitions, static fn (array $definition): bool => true !== ($definition['lecture_seule'] ?? false));

        return $this->namedFields($source, array_keys($definitions));
    }

    /**
     * @param list<string> $names
     *
     * @return array<string, mixed>
     */
    private function namedFields(object $source, array $names): array
    {
        $values = [];
        foreach ($names as $name) {
            $values[$name] = $this->normalize($source->{$name}());
        }

        return $values;
    }

    private function normalize(mixed $value): mixed
    {
        return match (true) {
            $value instanceof \BackedEnum => $value->value,
            $value instanceof \DateTimeInterface => $value->format('Y-m-d'),
            default => $value,
        };
    }
}
