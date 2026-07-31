<?php

declare(strict_types=1);

namespace App\Pim\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Pim\Api\Dto\LieuListResource;
use App\Pim\Api\Exception\ApiProblemException;
use App\Pim\Api\LieuApiMapper;
use App\Pim\Enum\StatutFiche;
use App\Pim\ReadModel\FicheCursor;
use App\Pim\Repository\LieuRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/** @implements ProviderInterface<LieuListResource> */
final readonly class LieuCollectionProvider implements ProviderInterface
{
    public function __construct(
        private LieuRepository $lieux,
        private LieuApiMapper $mapper,
        private RequestStack $requests,
    ) {
    }

    /** @return list<LieuListResource> */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
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

        $page = $this->lieux->findListPage($cursor, $limit, $status);
        if (null !== $request) {
            $request->attributes->set('_api_next_cursor', $page->nextCursor);
        }

        return array_map($this->mapper->listItem(...), $page->items);
    }
}
