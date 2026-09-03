<?php

declare(strict_types=1);

namespace App\Pim\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Pim\Api\Dto\RestaurantListResource;
use App\Pim\Api\Exception\ApiProblemException;
use App\Pim\Api\RestaurantApiMapper;
use App\Pim\Enum\StatutFiche;
use App\Pim\ReadModel\FicheCursor;
use App\Pim\Repository\RestaurantRepository;
use Symfony\Component\HttpFoundation\RequestStack;

/** @implements ProviderInterface<RestaurantListResource> */
final readonly class RestaurantCollectionProvider implements ProviderInterface
{
    public function __construct(
        private RestaurantRepository $restaurants,
        private RestaurantApiMapper $mapper,
        private RequestStack $requests,
    ) {
    }

    /** @return list<RestaurantListResource> */
    public function provide(
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): array {
        $request = $this->requests->getCurrentRequest();
        $statusValue = $request?->query->getString('status') ?? '';
        $status = '' === $statusValue
            ? null
            : StatutFiche::tryFrom($statusValue);
        if ('' !== $statusValue && null === $status) {
            throw new ApiProblemException(400, 'invalid_filter', 'Le filtre status est invalide.');
        }

        $limit = $request?->query->getInt('limit', 50) ?? 50;
        if ($limit < 1 || $limit > 100) {
            throw new ApiProblemException(400, 'invalid_filter', 'Le paramètre limit doit être compris entre 1 et 100.');
        }

        try {
            $cursor = FicheCursor::decode(
                $request?->query->getString('cursor'),
            );
        } catch (\InvalidArgumentException $exception) {
            throw new ApiProblemException(400, 'invalid_cursor', $exception->getMessage());
        }

        $page = $this->restaurants->findListPage($cursor, $limit, $status);
        $request?->attributes->set('_api_next_cursor', $page->nextCursor);

        return array_map($this->mapper->listItem(...), $page->items);
    }
}
