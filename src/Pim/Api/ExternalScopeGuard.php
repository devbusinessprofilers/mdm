<?php

declare(strict_types=1);

namespace App\Pim\Api;

use App\Account\Security\ExternalSitePrincipal;
use App\Pim\Api\Exception\ApiProblemException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate de scopes JWT pour les mutations fiches/médias de l'API externe, sur le
 * modèle d'ExternalDocumentAccess : le firewall garantit l'authentification,
 * les scopes portent l'autorisation fine (un jeton lecture seule ne doit pas
 * pouvoir muter).
 */
final readonly class ExternalScopeGuard
{
    public const FICHES_WRITE = 'fiches:write';
    public const MEDIAS_WRITE = 'medias:write';

    public function __construct(private Security $security)
    {
    }

    public function requireScope(string $scope): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof ExternalSitePrincipal) {
            throw new ApiProblemException(Response::HTTP_FORBIDDEN, 'insufficient_scope', 'Jeton externe requis.');
        }
        if (!$user->hasScope($scope)) {
            throw new ApiProblemException(Response::HTTP_FORBIDDEN, 'insufficient_scope', 'Le scope '.$scope.' est requis.');
        }
    }
}
