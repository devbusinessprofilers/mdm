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

final readonly class GlobalSearchProvider
{
    public function __construct(
        private SearchEngineInterface $searchEngine,
        private FicheRepository $fiches,
        private FicheRouteResolver $routes,
    ) {
    }

    public function search(
        string $text,
        ?TypeFiche $type = null,
        ?StatutFiche $status = null,
        int $limit = 50,
        ?string $cursor = null,
        ?string $country = null,
        ?int $completenessMin = null,
        ?int $completenessMax = null,
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
        if (null !== $country && 1 !== preg_match('/^[A-Z]{2}$/', $country)) {
            throw new \InvalidArgumentException('Pays invalide.');
        }
        foreach ([$completenessMin, $completenessMax] as $bound) {
            if (null !== $bound && ($bound < 0 || $bound > 100)) {
                throw new \InvalidArgumentException('Complétude invalide.');
            }
        }
        if (null !== $completenessMin && null !== $completenessMax && $completenessMin > $completenessMax) {
            throw new \InvalidArgumentException('Complétude invalide.');
        }

        $filters = [
            'type' => null === $type
                ? array_map(static fn (TypeFiche $value): string => $value->value, self::supportedTypes())
                : $type->value,
        ];
        if (null !== $status) {
            $filters['status'] = $status->value;
        }
        if (null !== $country) {
            $filters['country'] = $country;
        }
        if (null !== $completenessMin) {
            $filters['completeness_min'] = $completenessMin;
        }
        if (null !== $completenessMax) {
            $filters['completeness_max'] = $completenessMax;
        }

        $page = $this->searchEngine->search(new SearchQuery($text, $filters, $limit, $cursor));
        $items = $this->fiches->findGlobalSearchItemsByIds(array_map(
            static fn ($result): string => $result->id,
            $page->results,
        ));
        $results = array_map(fn ($item): GlobalSearchResult => new GlobalSearchResult(
            $item,
            $this->routes->showUrl($item->type, $item->id),
            $this->routes->editUrl($item->type, $item->id),
        ), $items);

        return new GlobalSearchPage($results, $page->nextCursor, $page->totalCount);
    }

    /** @return list<TypeFiche> */
    private static function supportedTypes(): array
    {
        return [TypeFiche::Lieu, TypeFiche::Activite, TypeFiche::Restaurant, TypeFiche::ServiceEvenementiel];
    }
}
