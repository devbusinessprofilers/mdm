<?php

declare(strict_types=1);

namespace App\Pim\Api\State;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Pim\Api\Dto\ActiviteResource;
use App\Pim\Api\Dto\LieuResource;
use App\Pim\Api\Dto\RestaurantResource;
use App\Pim\Api\Dto\ServiceEvenementielResource;
use App\Pim\Api\FicheApiMapper;
use App\Pim\Enum\TypeFiche;

/**
 * GET /v1/{gamme}/{id} : la gamme est celle du segment d'URL de l'opération.
 *
 * @implements ProviderInterface<LieuResource|RestaurantResource|ActiviteResource|ServiceEvenementielResource>
 */
final readonly class FicheItemProvider implements ProviderInterface
{
    public function __construct(private FicheApiState $state, private FicheApiMapper $mapper)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): LieuResource|RestaurantResource|ActiviteResource|ServiceEvenementielResource
    {
        $type = self::gamme($operation);

        return $this->mapper->fiche($this->state->entite($type, (string) ($uriVariables['id'] ?? '')));
    }

    /** Gamme d'une opération de fiche, lue dans son uriTemplate (`/v1/lieux/{id}`). */
    public static function gamme(Operation $operation): TypeFiche
    {
        $template = $operation instanceof HttpOperation ? (string) $operation->getUriTemplate() : '';
        $segments = explode('/', trim($template, '/'));
        $type = isset($segments[1]) ? TypeFiche::depuisSlug($segments[1]) : null;

        return $type ?? throw new \LogicException(sprintf('Gamme inconnue dans l’opération %s.', $template));
    }
}
