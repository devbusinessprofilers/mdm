<?php

declare(strict_types=1);

namespace App\Shared\Service;

use App\Shared\Search\SearchPage;
use App\Shared\Search\SearchQuery;

interface SearchEngineInterface
{
    public function search(SearchQuery $query): SearchPage;
}
