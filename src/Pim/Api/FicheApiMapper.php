<?php

declare(strict_types=1);

namespace App\Pim\Api;

use App\Dam\Service\FichePhotoPresenter;
use App\Pim\Api\Dto\ActiviteListResource;
use App\Pim\Api\Dto\ActiviteResource;
use App\Pim\Api\Dto\FicheMediaResource;
use App\Pim\Api\Dto\LieuListResource;
use App\Pim\Api\Dto\LieuResource;
use App\Pim\Api\Dto\RestaurantListResource;
use App\Pim\Api\Dto\RestaurantResource;
use App\Pim\Api\Dto\ServiceEvenementielListResource;
use App\Pim\Api\Dto\ServiceEvenementielResource;
use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Pim\ReadModel\ActiviteListItem;
use App\Pim\ReadModel\LieuListItem;
use App\Pim\ReadModel\RestaurantListItem;
use App\Pim\ReadModel\ServiceEvenementielListItem;

/**
 * Point d'entrée des mappers de l'API externe : la représentation d'une fiche
 * et d'une ligne de liste reste propre à chaque gamme (champs), celle d'un
 * média est la même pour toutes.
 */
final readonly class FicheApiMapper
{
    public function __construct(
        private LieuApiMapper $lieux,
        private RestaurantApiMapper $restaurants,
        private ActiviteApiMapper $activites,
        private ServiceEvenementielApiMapper $services,
        private FichePhotoPresenter $photos,
        private MediaResourceFactory $medias,
    ) {
    }

    public function fiche(Lieu|Restaurant|Activite|ServiceEvenementiel $entite): LieuResource|RestaurantResource|ActiviteResource|ServiceEvenementielResource
    {
        return match (true) {
            $entite instanceof Lieu => $this->lieux->lieu($entite),
            $entite instanceof Restaurant => $this->restaurants->restaurant($entite),
            $entite instanceof Activite => $this->activites->activite($entite),
            default => $this->services->service($entite),
        };
    }

    public function listItem(LieuListItem|RestaurantListItem|ActiviteListItem|ServiceEvenementielListItem $item): LieuListResource|RestaurantListResource|ActiviteListResource|ServiceEvenementielListResource
    {
        return match (true) {
            $item instanceof LieuListItem => $this->lieux->listItem($item),
            $item instanceof RestaurantListItem => $this->restaurants->listItem($item),
            $item instanceof ActiviteListItem => $this->activites->listItem($item),
            default => $this->services->listItem($item),
        };
    }

    /** Une photo avec ses rendus DAM quand ils existent (sans URL ni variante sinon). */
    public function media(Lieu|Restaurant|Activite|ServiceEvenementiel $entite, RessourceLieu $resource): FicheMediaResource
    {
        $version = $entite->fiche()->version();
        foreach ($this->photos->photos($entite->fiche()) as $photo) {
            if ($photo['resource'] === $resource) {
                return $this->medias->depuisPresentation($version, $photo);
            }
        }

        return $this->medias->depuisRessource($version, $resource);
    }
}
