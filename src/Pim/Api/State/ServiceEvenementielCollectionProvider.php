<?php

declare(strict_types=1);

namespace App\Pim\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Pim\Api\ServiceEvenementielApiMapper;
use App\Pim\Api\Dto\ServiceEvenementielListResource;
use App\Pim\Api\Exception\ApiProblemException;
use App\Pim\Enum\StatutFiche;
use App\Pim\ReadModel\FicheCursor;
use App\Pim\Repository\ServiceEvenementielRepository;
use Symfony\Component\HttpFoundation\RequestStack;

/** @implements ProviderInterface<ServiceEvenementielListResource> */
final readonly class ServiceEvenementielCollectionProvider implements
    ProviderInterface
{
    public function __construct(
        private ServiceEvenementielRepository $services,
        private ServiceEvenementielApiMapper $mapper,
        private RequestStack $requests,
    ) {}

    /** @return list<ServiceEvenementielListResource> */
    public function provide(
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): array {
        $r = $this->requests->getCurrentRequest();
        $s = $r?->query->getString("status") ?? "";
        $status = "" === $s ? null : StatutFiche::tryFrom($s);
        if ("" !== $s && null === $status) {
            throw new ApiProblemException(
                400,
                "invalid_filter",
                "Le filtre status est invalide.",
            );
        }
        $limit = $r?->query->getInt("limit", 50) ?? 50;
        if ($limit < 1 || $limit > 100) {
            throw new ApiProblemException(
                400,
                "invalid_filter",
                "Le paramètre limit doit être compris entre 1 et 100.",
            );
        }
        try {
            $cursor = FicheCursor::decode($r?->query->getString("cursor"));
        } catch (\InvalidArgumentException $e) {
            throw new ApiProblemException(
                400,
                "invalid_cursor",
                $e->getMessage(),
            );
        }
        $page = $this->services->findListPage($cursor, $limit, $status);
        $r?->attributes->set("_api_next_cursor", $page->nextCursor);

        return array_map($this->mapper->listItem(...), $page->items);
    }
}
