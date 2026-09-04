<?php

declare(strict_types=1);

namespace App\Pim\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Pim\Api\Dto\ActiviteListResource;
use App\Pim\Api\Dto\LieuListResource;
use App\Pim\Api\Dto\RestaurantListResource;
use App\Pim\Api\Dto\ServiceEvenementielListResource;
use App\Pim\Api\Exception\ApiProblemException;
use App\Pim\Api\FicheApiMapper;
use App\Pim\Enum\StatutFiche;
use App\Pim\ReadModel\FicheCursor;
use App\Pim\Service\FicheDetailResolver;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET /v1/{gamme} : liste paginée par curseur, filtrable par statut.
 *
 * @implements ProviderInterface<LieuListResource|RestaurantListResource|ActiviteListResource|ServiceEvenementielListResource>
 */
final readonly class FicheCollectionProvider implements ProviderInterface
{
    public function __construct(
        private FicheDetailResolver $details,
        private FicheApiMapper $mapper,
        private RequestStack $requests,
    ) {
    }

    /** @return list<LieuListResource|RestaurantListResource|ActiviteListResource|ServiceEvenementielListResource> */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $type = FicheItemProvider::gamme($operation);
        $request = $this->requests->getCurrentRequest();
        $statusValue = $request?->query->getString('status') ?? '';
        $status = '' === $statusValue ? null : StatutFiche::tryFrom($statusValue);
        if ('' !== $statusValue && null === $status) {
            throw new ApiProblemException(Response::HTTP_BAD_REQUEST, 'invalid_filter', 'Le filtre status est invalide.');
        }
        $limit = $request?->query->getInt('limit', 50) ?? 50;
        if ($limit < 1 || $limit > 100) {
            throw new ApiProblemException(Response::HTTP_BAD_REQUEST, 'invalid_filter', 'Le paramètre limit doit être compris entre 1 et 100.');
        }
        try {
            $cursor = FicheCursor::decode($request?->query->getString('cursor'));
        } catch (\InvalidArgumentException $exception) {
            throw new ApiProblemException(Response::HTTP_BAD_REQUEST, 'invalid_cursor', $exception->getMessage());
        }
        $page = $this->details->repository($type)->findListPage($cursor, $limit, $status);
        $request?->attributes->set('_api_next_cursor', $page->nextCursor);

        return array_map($this->mapper->listItem(...), $page->items);
    }
}
