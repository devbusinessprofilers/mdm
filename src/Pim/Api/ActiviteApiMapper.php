<?php

declare(strict_types=1);

namespace App\Pim\Api;

use App\Dam\Service\FichePhotoPresenter;
use App\Pim\Api\Dto\ActiviteListResource;
use App\Pim\Api\Dto\ActiviteResource;
use App\Pim\Api\Dto\LieuMediaResource;
use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\ReadModel\ActiviteListItem;

final readonly class ActiviteApiMapper
{
    public function __construct(private FichePhotoPresenter $photos)
    {
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
                fn (array $photo): LieuMediaResource => $this->photo($a, $photo),
                $this->photos->photos($f),
            ),
        );
    }

    public function media(
        Activite $a,
        RessourceLieu $resource,
    ): LieuMediaResource {
        foreach ($this->photos->photos($a->fiche()) as $photo) {
            if ($photo['resource'] === $resource) {
                return $this->photo($a, $photo);
            }
        }

        return new LieuMediaResource(
            $resource->id(),
            $a->fiche()->version(),
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
            $resource->rightsValidity()->value,
        );
    }

    /** @param array{resource:RessourceLieu,asset:\App\Dam\Entity\MediaAsset|null,url:string|null,thumbnail_url:string|null,variants:list<array{name:string,width:int,height:int,url:string|null}>} $photo */
    private function photo(Activite $a, array $photo): LieuMediaResource
    {
        $r = $photo['resource'];

        return new LieuMediaResource(
            $r->id(),
            $a->fiche()->version(),
            $r->damAssetId(),
            $r->usage(),
            $r->legende(),
            $r->position(),
            $r->rightsGranted(),
            $r->source(),
            $r->crop(),
            $r->rotation(),
            $photo['asset']?->status()->value,
            $photo['url'],
            $photo['variants'],
            $r->keywords(),
            $r->rightsExpiresAt()?->format('Y-m-d'),
            $r->rightsValidity()->value,
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
