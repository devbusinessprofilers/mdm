<?php

declare(strict_types=1);

namespace App\Pim\Api;

use App\Dam\Entity\MediaAsset;
use App\Pim\Api\Dto\FicheMediaResource;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Shared\Service\ParametreProviderInterface;

/**
 * Représentation API d'une photo, la même pour toutes les gammes : depuis
 * la présentation DAM (statut, URL, rendus) quand elle existe, depuis la seule
 * ressource sinon.
 */
final readonly class MediaResourceFactory
{
    public function __construct(private ParametreProviderInterface $parametres)
    {
    }

    /** @param array{resource: RessourceLieu, asset: MediaAsset|null, url: string|null, variants: list<array{name: string, width: int, height: int, url: string|null}>} $photo */
    public function depuisPresentation(int $version, array $photo): FicheMediaResource
    {
        return $this->creer($version, $photo['resource'], $photo['asset']?->status()->value, $photo['url'], $photo['variants']);
    }

    public function depuisRessource(int $version, RessourceLieu $resource): FicheMediaResource
    {
        return $this->creer($version, $resource, null, null, []);
    }

    /** @param list<array{name: string, width: int, height: int, url: string|null}> $variants */
    private function creer(int $version, RessourceLieu $resource, ?string $status, ?string $url, array $variants): FicheMediaResource
    {
        return new FicheMediaResource(
            $resource->id(),
            $version,
            $resource->damAssetId(),
            $resource->usage(),
            $resource->legende(),
            $resource->position(),
            $resource->rightsGranted(),
            $resource->source(),
            $resource->crop(),
            $resource->rotation(),
            $status,
            $url,
            $variants,
            $resource->keywords(),
            $resource->rightsExpiresAt()?->format('Y-m-d'),
            $resource->rightsValidity(alerteJours: $this->parametres->int('dam.delai_alerte_droits_jours'))->value,
        );
    }
}
