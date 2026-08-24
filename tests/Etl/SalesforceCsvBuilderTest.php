<?php

declare(strict_types=1);

namespace App\Tests\Etl;

use App\Etl\Service\SalesforceCsvBuilder;
use PHPUnit\Framework\TestCase;

final class SalesforceCsvBuilderTest extends TestCase
{
    public function testEveryFieldIsQuotedAndRowsEndWithCrlf(): void
    {
        $csv = SalesforceCsvBuilder::build(['A', 'B'], [['1', 'x']]);

        self::assertSame("\"A\",\"B\"\r\n\"1\",\"x\"\r\n", $csv);
    }

    public function testEmbeddedQuotesAreDoubledAndNewlinesPreserved(): void
    {
        $csv = SalesforceCsvBuilder::build(['T'], [["dit \"Cinq S\"\nsuite"]]);

        // Guillemets internes doublés, saut de ligne conservé dans le champ.
        self::assertSame("\"T\"\r\n\"dit \"\"Cinq S\"\"\nsuite\"\r\n", $csv);
    }

    public function testCommasInsideFieldsDoNotSplitColumns(): void
    {
        $csv = SalesforceCsvBuilder::build(['T'], [['a,b,c']]);

        self::assertSame("\"T\"\r\n\"a,b,c\"\r\n", $csv);
    }
}
