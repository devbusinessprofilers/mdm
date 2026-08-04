<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use PHPUnit\Framework\TestCase;

final class LovAdminQueryRegressionTest extends TestCase
{
    public function testPaginationUsesBoundIntegerParametersWithWhitespace(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2).'/src/Pim/Repository/AttributDefinitionRepository.php');

        self::assertStringContainsString('LIMIT :limit OFFSET :offset', $source);
        self::assertStringContainsString("'limit' => ParameterType::INTEGER", $source);
        self::assertStringContainsString("'offset' => ParameterType::INTEGER", $source);
        self::assertStringNotContainsString('OFFSET0', str_replace(' ', '', $source));
    }
}
