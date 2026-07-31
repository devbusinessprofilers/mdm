<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Service\AdminWorkflowCatalog;
use PHPUnit\Framework\TestCase;

final class AdminWorkflowCatalogTest extends TestCase
{
    public function testItDocumentsPimApiDamAndOperationsWorkflows(): void
    {
        $workflows = (new AdminWorkflowCatalog())->workflows();
        self::assertSame(
            ['PIM', 'API', 'DAM', 'Audit', 'Ops'],
            array_column($workflows, 'domain'),
        );
        self::assertStringContainsString(
            'If-Match',
            implode(' ', $workflows[1]['steps']),
        );
        self::assertStringContainsString(
            'Restaurant',
            implode(' ', $workflows[0]['steps']),
        );
        self::assertStringContainsString(
            'documents:private',
            implode(' ', $workflows[2]['rules']),
        );
        self::assertStringContainsString(
            'append-only',
            $workflows[3]['summary'],
        );
        self::assertStringContainsString(
            'doctrine:migrations:migrate',
            implode(' ', $workflows[4]['steps']),
        );
        foreach ($workflows as $workflow) {
            self::assertNotSame('', $workflow['summary']);
            self::assertNotEmpty($workflow['steps']);
            self::assertNotEmpty($workflow['rules']);
        }
    }
}
