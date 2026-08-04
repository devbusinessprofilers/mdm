<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Enum\StatutFiche;
use App\Pim\Enum\TypeFiche;
use App\Pim\ReadModel\GlobalSearchPage;
use App\Pim\ReadModel\GlobalSearchResult;
use App\Pim\Repository\FicheRepository;
use App\Shared\Search\SearchQuery;
use App\Shared\Service\SearchEngineInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class GlobalSearchProvider
{
    public function __construct(
        private SearchEngineInterface $searchEngine,
        private FicheRepository $fiches,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function search(
        string $text,
        ?TypeFiche $type = null,
        ?StatutFiche $status = null,
        int $limit = 50,
        ?string $cursor = null,
    ): GlobalSearchPage {
        $text = trim($text);
        if ('' === $text) {
            if (null !== $cursor) {
                throw new \InvalidArgumentException('Un curseur exige une recherche.');
            }

            return new GlobalSearchPage([], null, 0);
        }
        if (TypeFiche::Traiteur === $type) {
            throw new \InvalidArgumentException('Type de fiche invalide.');
        }

        $filters = [
            'type' => null === $type
                ? array_map(static fn (TypeFiche $value): string => $value->value, self::supportedTypes())
                : $type->value,
        ];
        if (null !== $status) {
            $filters['status'] = $status->value;
        }

        $page = $this->searchEngine->search(new SearchQuery($text, $filters, $limit, $cursor));
        $items = $this->fiches->findGlobalSearchItemsByIds(array_map(
            static fn ($result): string => $result->id,
            $page->results,
        ));
        $results = array_map(function ($item): GlobalSearchResult {
            [$showRoute, $editRoute] = self::routesFor($item->type);

            return new GlobalSearchResult(
                $item,
                $this->urlGenerator->generate($showRoute, ['id' => $item->id]),
                $this->urlGenerator->generate($editRoute, ['id' => $item->id]),
            );
        }, $items);

        return new GlobalSearchPage($results, $page->nextCursor, $page->totalCount);
    }

    /** @return list<TypeFiche> */
    private static function supportedTypes(): array
    {
        return [TypeFiche::Lieu, TypeFiche::Activite, TypeFiche::Restaurant, TypeFiche::ServiceEvenementiel];
    }

    /** @return array{string, string} */
    private static function routesFor(TypeFiche $type): array
    {
        return match ($type) {
            TypeFiche::Lieu => ['app_pim_lieu_show', 'app_pim_lieu_edit'],
            TypeFiche::Activite => ['app_pim_activite_show', 'app_pim_activite_edit'],
            TypeFiche::Restaurant => ['app_pim_restaurant_show', 'app_pim_restaurant_edit'],
            TypeFiche::ServiceEvenementiel => ['app_pim_service_show', 'app_pim_service_edit'],
            TypeFiche::Traiteur => throw new \InvalidArgumentException('Type de fiche invalide.'),
        };
    }
}
