<?php

declare(strict_types=1);

namespace App\Pim\Api;

use App\Dam\Service\FichePhotoPresenter;
use App\Pim\Api\Dto\ActiviteListResource;
use App\Pim\Api\Dto\ActiviteResource;
use App\Pim\Api\Dto\FicheMediaResource;
use App\Pim\Entity\Activite\Activite;
use App\Pim\ReadModel\ActiviteListItem;

final readonly class ActiviteApiMapper
{
    public function __construct(
        private FichePhotoPresenter $photos,
        private MediaResourceFactory $medias,
    ) {
    }

    public function listItem(ActiviteListItem $i): ActiviteListResource
    {
        return new ActiviteListResource(
            $i->id,
            $i->code,
            $i->label,
            $i->ville,
            $i->status->value,
            $i->completeness,
            $i->updatedAt->format(DATE_ATOM),
        );
    }

    public function activite(Activite $a): ActiviteResource
    {
        $f = $a->fiche();
        $fixed = 'fixe' === $a->modeIntervention()?->value;
        $l = $fixed ? $a->localisation() : null;

        return new ActiviteResource(
            $a->id(),
            $a->code(),
            $a->label(),
            $f->status()->value,
            $a->completeness(),
            $a->completenessByChannel(),
            $a->completenessCalculatedAt()?->format(DATE_ATOM),
            $f->version(),
            $f->publishedAt()?->format(DATE_ATOM),
            $f->updatedAt()->format(DATE_ATOM),
            null === $a->prestataire()
                ? null
                : [
                    'code' => $a->prestataire()->code(),
                    'label' => $a->prestataire()->label(),
                ],
            $a->types(),
            $a->thematiques(),
            $a->sousThematiques(),
            $a->langues(),
            $a->engagementsRse(),
            $a->modeIntervention()?->value,
            $fixed ? false : $a->touteFrance(),
            $fixed ? [] : $a->paysMobiles(),
            $fixed ? [] : $a->regionsMobiles(),
            $fixed ? [] : $a->departementsMobiles(),
            null === $l
                ? null
                : $this->values($l, [
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
            $a->descriptionGenerale(),
            $a->comprendPrestation(),
            $a->objectifs(),
            $a->participantsMin(),
            $a->participantsMax(),
            $a->dureeMinMinutes(),
            $a->dureeMaxMinutes(),
            $a->plus(),
            $a->tarifParPersonne(),
            $a->youtubeUrl(),
            array_values(
                array_map(
                    fn ($o) => $this->values($o, [
                        'id',
                        'type',
                        'nom',
                        'participantsMin',
                        'participantsMax',
                        'prix',
                        'modeTarification',
                        'position',
                    ]),
                    $a->offres()->toArray(),
                ),
            ),
            array_map(
                fn (array $photo): FicheMediaResource => $this->medias->depuisPresentation($f->version(), $photo),
                $this->photos->photos($f),
            ),
        );
    }

    /**
     * @param list<string> $fields
     *
     * @return array<string,mixed>
     */
    private function values(object $o, array $fields): array
    {
        $r = [];
        foreach ($fields as $field) {
            $v = $o->{$field}();
            $r[$field] =
                $v instanceof \BackedEnum
                    ? $v->value
                    : ($v instanceof \DateTimeInterface
                        ? $v->format('Y-m-d')
                        : $v);
        }

        return $r;
    }
}
