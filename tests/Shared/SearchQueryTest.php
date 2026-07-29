<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Shared\Search\SearchQuery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SearchQueryTest extends TestCase
{
    /** @return iterable<string, array{int}> */
    public static function invalidLimits(): iterable
    {
        yield 'zero' => [0];
        yield 'more than one page maximum' => [101];
    }

    #[DataProvider('invalidLimits')]
    public function testLimitIsBounded(int $limit): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SearchQuery('lieu', limit: $limit);
    }
}
